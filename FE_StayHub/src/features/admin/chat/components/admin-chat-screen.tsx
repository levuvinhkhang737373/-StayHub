import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { createPortal } from 'react-dom'
import { useSearchParams } from 'react-router-dom'
import { ChevronLeft, ChevronRight, Image as ImageIcon, Loader2, MessageCircle, Search, Send, X, ZoomIn, ZoomOut } from 'lucide-react'
import { useAdminSocket } from '../../../../shared/lib/socket/socket-context'
import { isBuildingManagerRole, isSuperAdminRole, useAdminSession } from '../../auth/hooks/use-admin-session'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatTimeOnly, getChatDividerLabel } from '../../../../shared/lib/utils/format'
import {
  fetchAdminChatConversations,
  fetchAdminChatMessages,
  fetchAdminDirectConversations,
  fetchAdminDirectMessages,
  markAdminChatRead,
  markAdminDirectRead,
  sendAdminChatMessage,
  sendAdminDirectMessage,
} from '../../../shared/chat/services/chat.service'
import type {
  ChatConversationReadEvent,
  ChatConversationResource,
  ChatMessageResource,
  ChatMessageSentEvent,
} from '../../../shared/chat/types/chat.types'

const ADMIN_ROLE = 2
const MESSAGES_PER_PAGE = 30
const SCROLL_UP_THRESHOLD = 80
const NEAR_BOTTOM_THRESHOLD = 200
const INITIAL_LOAD_BLOCK_MS = 300

type ChatTab = 'tenants' | 'direct'
type UnifiedMessage = ChatMessageResource
type UnifiedConversation = ChatConversationResource

function isDirectConversation(conversation: UnifiedConversation | null): boolean {
  return Number(conversation?.conversation_type) === 2
}

function messageSenderRole(message: UnifiedMessage) {
  return Number(message.sender_role)
}

function messageIsMine(message: UnifiedMessage, adminId?: number) {
  if (!adminId) return false
  return message.sender_type === 'admin' && Number(message.sender_id) === Number(adminId)
}

function conversationSortValue(conversation: UnifiedConversation) {
  return new Date(conversation.last_message_at || conversation.updated_at || 0).getTime()
}

function getDirectUnread(conversation: ChatConversationResource, adminId?: number) {
  if (!adminId) return 0
  return Number(conversation.super_admin_id) === Number(adminId)
    ? Number(conversation.admin_unread_count || 0)
    : Number(conversation.tenant_unread_count || 0)
}

export function AdminChatScreen() {
  const { echo } = useAdminSocket()
  const { session } = useAdminSession()
  const adminId = session?.admin?.id
  const isSuperAdmin = isSuperAdminRole(session?.admin?.role)
  const isBuildingManager = isBuildingManagerRole(session?.admin?.role)
  const [searchParams] = useSearchParams()
  const tenantIdParam = searchParams.get('tenant_id')
  const conversationIdParam = searchParams.get('conversation_id')
  const directConversationIdParam = searchParams.get('direct_conversation_id')
  const requestedTab = searchParams.get('tab') as ChatTab | null
  const defaultTab: ChatTab = isSuperAdmin ? 'direct' : requestedTab === 'direct' ? 'direct' : 'tenants'

  const [activeTab, setActiveTab] = useState<ChatTab>(defaultTab)
  const [tenantConversations, setTenantConversations] = useState<ChatConversationResource[]>([])
  const [directConversations, setDirectConversations] = useState<ChatConversationResource[]>([])
  const [activeTenantConversation, setActiveTenantConversation] = useState<ChatConversationResource | null>(null)
  const [activeDirectConversation, setActiveDirectConversation] = useState<ChatConversationResource | null>(null)
  const [tenantMessages, setTenantMessages] = useState<ChatMessageResource[]>([])
  const [directMessages, setDirectMessages] = useState<ChatMessageResource[]>([])
  const [keyword, setKeyword] = useState('')
  const [showUnreadOnly, setShowUnreadOnly] = useState(false)
  const [isLoadingConversations, setIsLoadingConversations] = useState(true)
  const [isLoadingMessages, setIsLoadingMessages] = useState(false)
  const [isSending, setIsSending] = useState(false)
  const [input, setInput] = useState('')
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [selectedImages, setSelectedImages] = useState<File[]>([])
  const [imagePreviews, setImagePreviews] = useState<string[]>([])
  const [hasMore, setHasMore] = useState(false)
  const [isLoadingMore, setIsLoadingMore] = useState(false)
  const [activeImageUrls, setActiveImageUrls] = useState<string[] | null>(null)
  const [activeImageIndex, setActiveImageIndex] = useState(0)
  const [scale, setScale] = useState(1)
  const [position, setPosition] = useState({ x: 0, y: 0 })
  const [isDragging, setIsDragging] = useState(false)
  const [dragStart, setDragStart] = useState({ x: 0, y: 0 })
  const messagesEndRef = useRef<HTMLDivElement | null>(null)
  const fileInputRef = useRef<HTMLInputElement | null>(null)
  const scrollContainerRef = useRef<HTMLDivElement | null>(null)
  const textareaRef = useRef<HTMLTextAreaElement | null>(null)
  const isInitialLoadRef = useRef(true)
  const markReadTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  const tenantUnread = useMemo(() => tenantConversations.reduce((total, item) => total + Number(item.admin_unread_count || 0), 0), [tenantConversations])
  const directUnread = useMemo(() => directConversations.reduce((total, item) => total + getDirectUnread(item, adminId), 0), [directConversations, adminId])
  const visibleConversations = activeTab === 'direct' ? directConversations : tenantConversations
  const activeConversation = activeTab === 'direct' ? activeDirectConversation : activeTenantConversation
  const messages = activeTab === 'direct' ? directMessages : tenantMessages
  const canUseTenantChat = isBuildingManager && !isSuperAdmin

  useEffect(() => {
    setActiveTab(isSuperAdmin ? 'direct' : requestedTab === 'direct' ? 'direct' : 'tenants')
  }, [isSuperAdmin, requestedTab])

  useEffect(() => {
    if (selectedImages.length === 0) {
      setImagePreviews([])
      return
    }
    const urls = selectedImages.map((file) => URL.createObjectURL(file))
    setImagePreviews(urls)
    return () => {
      urls.forEach((url) => URL.revokeObjectURL(url))
    }
  }, [selectedImages])

  useEffect(() => {
    if (activeImageUrls) {
      document.body.style.overflow = 'hidden'
      document.documentElement.style.overflow = 'hidden'
    } else {
      document.body.style.overflow = ''
      document.documentElement.style.overflow = ''
      setScale(1)
      setPosition({ x: 0, y: 0 })
    }
    return () => {
      document.body.style.overflow = ''
      document.documentElement.style.overflow = ''
    }
  }, [activeImageUrls])

  useEffect(() => {
    setScale(1)
    setPosition({ x: 0, y: 0 })
  }, [activeImageIndex, activeImageUrls])

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (!activeImageUrls) return
      if (e.key === 'ArrowLeft' && activeImageIndex > 0) {
        setActiveImageIndex((prev) => prev - 1)
      } else if (e.key === 'ArrowRight' && activeImageIndex < activeImageUrls.length - 1) {
        setActiveImageIndex((prev) => prev + 1)
      } else if (e.key === 'Escape') {
        setActiveImageUrls(null)
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [activeImageUrls, activeImageIndex])

  useEffect(() => {
    const textarea = textareaRef.current
    if (textarea) {
      textarea.style.height = 'auto'
      textarea.style.height = `${textarea.scrollHeight}px`
    }
  }, [input])

  const upsertTenantConversation = useCallback((conversation: ChatConversationResource) => {
    setTenantConversations((current) => [conversation, ...current.filter((item) => item.id !== conversation.id)].sort((left, right) => conversationSortValue(right) - conversationSortValue(left)))
    setActiveTenantConversation((current) => current?.id === conversation.id ? { ...current, ...conversation } : current)
  }, [])

  const upsertDirectConversation = useCallback((conversation: ChatConversationResource) => {
    setDirectConversations((current) => [conversation, ...current.filter((item) => item.id !== conversation.id)].sort((left, right) => conversationSortValue(right) - conversationSortValue(left)))
    setActiveDirectConversation((current) => current?.id === conversation.id ? { ...current, ...conversation } : current)
  }, [])

  const loadTenantConversations = useCallback(async () => {
    if (!canUseTenantChat) {
      setTenantConversations([])
      setActiveTenantConversation(null)
      return
    }

    const response = await fetchAdminChatConversations({
      keyword: keyword || undefined,
      unread: showUnreadOnly ? 1 : undefined,
      per_page: 50,
    })
    const data = response.result?.data || []
    setTenantConversations(data)

    let targetConversation: ChatConversationResource | null = null
    if (conversationIdParam) {
      targetConversation = data.find((item) => Number(item.id) === Number(conversationIdParam)) || null
    } else if (tenantIdParam) {
      targetConversation = data.find((item) => Number(item.tenant_id) === Number(tenantIdParam)) || null
    }

    setActiveTenantConversation((current) => {
      if (targetConversation) return targetConversation
      return current && data.some((item) => item.id === current.id) ? current : data[0] || null
    })
  }, [canUseTenantChat, conversationIdParam, keyword, showUnreadOnly, tenantIdParam])

  const loadDirectConversations = useCallback(async () => {
    if (!adminId) return

    const response = await fetchAdminDirectConversations({
      keyword: keyword || undefined,
      unread: showUnreadOnly ? 1 : undefined,
      per_page: 100,
    })
    const data = response.result?.data || []
    setDirectConversations(data)

    const targetConversation = directConversationIdParam
      ? data.find((item) => Number(item.id) === Number(directConversationIdParam)) || null
      : null

    setActiveDirectConversation((current) => {
      if (targetConversation) return targetConversation
      return current && data.some((item) => item.id === current.id) ? current : data[0] || null
    })
  }, [adminId, directConversationIdParam, keyword, showUnreadOnly])

  const loadConversations = useCallback(async () => {
    setIsLoadingConversations(true)
    setErrorMessage(null)
    try {
      if (activeTab === 'direct') {
        await loadDirectConversations()
      } else {
        await loadTenantConversations()
      }
    } catch (error: any) {
      setErrorMessage(error?.message || 'Không thể tải danh sách đoạn chat.')
    } finally {
      setIsLoadingConversations(false)
    }
  }, [activeTab, loadDirectConversations, loadTenantConversations])

  const loadTenantMessages = useCallback(async (conversation: ChatConversationResource) => {
    const response = await fetchAdminChatMessages(conversation.id, { per_page: MESSAGES_PER_PAGE })
    setTenantMessages(response.result?.data || [])
    setHasMore(response.result?.pagination?.has_more || false)

    if (conversation.admin_unread_count > 0) {
      const readResponse = await markAdminChatRead(conversation.id)
      if (readResponse.result) upsertTenantConversation(readResponse.result)
    }
  }, [upsertTenantConversation])

  const loadDirectMessages = useCallback(async (conversation: ChatConversationResource) => {
    const response = await fetchAdminDirectMessages(conversation.id, { per_page: MESSAGES_PER_PAGE })
    setDirectMessages(response.result?.data || [])
    setHasMore(response.result?.pagination?.has_more || false)

    if (getDirectUnread(conversation, adminId) > 0) {
      const readResponse = await markAdminDirectRead(conversation.id)
      if (readResponse.result) upsertDirectConversation(readResponse.result)
    }
  }, [adminId, upsertDirectConversation])

  const loadMessages = useCallback(async (conversation: UnifiedConversation) => {
    setIsLoadingMessages(true)
    isInitialLoadRef.current = true
    setErrorMessage(null)
    try {
      if (isDirectConversation(conversation)) {
        await loadDirectMessages(conversation)
      } else {
        await loadTenantMessages(conversation)
      }

      window.requestAnimationFrame(() => {
        if (scrollContainerRef.current) {
          scrollContainerRef.current.scrollTop = scrollContainerRef.current.scrollHeight
        }
        window.setTimeout(() => {
          isInitialLoadRef.current = false
        }, INITIAL_LOAD_BLOCK_MS)
      })
    } catch (error: any) {
      setErrorMessage(error?.message || 'Không thể tải tin nhắn.')
    } finally {
      setIsLoadingMessages(false)
    }
  }, [loadDirectMessages, loadTenantMessages])

  const loadMoreMessages = useCallback(async () => {
    if (!activeConversation || isLoadingMore || !hasMore || messages.length === 0) return

    setIsLoadingMore(true)
    const oldestId = messages[0]?.id

    try {
      const container = scrollContainerRef.current
      const prevScrollHeight = container ? container.scrollHeight : 0
      const response = isDirectConversation(activeConversation)
        ? await fetchAdminDirectMessages(activeConversation.id, { before_id: oldestId, per_page: MESSAGES_PER_PAGE })
        : await fetchAdminChatMessages(activeConversation.id, { before_id: oldestId, per_page: MESSAGES_PER_PAGE })
      const olderMessages = response.result?.data || []
      setHasMore(response.result?.pagination?.has_more || false)

      if (olderMessages.length > 0) {
        if (isDirectConversation(activeConversation)) {
          setDirectMessages((current) => [...(olderMessages as ChatMessageResource[]), ...current])
        } else {
          setTenantMessages((current) => [...(olderMessages as ChatMessageResource[]), ...current])
        }

        window.requestAnimationFrame(() => {
          if (container) {
            container.scrollTop = container.scrollHeight - prevScrollHeight
          }
        })
      }
    } catch (error: any) {
      setErrorMessage(error?.message || 'Không thể tải thêm tin nhắn.')
    } finally {
      setIsLoadingMore(false)
    }
  }, [activeConversation, hasMore, isLoadingMore, messages])

  const handleScroll = () => {
    const container = scrollContainerRef.current
    if (!container || isInitialLoadRef.current) return
    if (container.scrollHeight <= container.clientHeight) return
    if (container.scrollTop < SCROLL_UP_THRESHOLD && hasMore && !isLoadingMore) {
      void loadMoreMessages()
    }
  }

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadConversations()
    }, 250)
    return () => window.clearTimeout(timer)
  }, [loadConversations])

  useEffect(() => {
    const conversation = activeTab === 'direct' ? activeDirectConversation : activeTenantConversation
    setInput('')
    setSelectedImages([])
    setErrorMessage(null)
    if (conversation) {
      void loadMessages(conversation)
    } else if (activeTab === 'direct') {
      setDirectMessages([])
    } else {
      setTenantMessages([])
    }
  }, [activeDirectConversation?.id, activeTab, activeTenantConversation?.id, loadMessages])

  useEffect(() => {
    if (tenantConversations.length === 0) return
    let targetConversation: ChatConversationResource | null = null
    if (conversationIdParam) {
      targetConversation = tenantConversations.find((item) => Number(item.id) === Number(conversationIdParam)) || null
    } else if (tenantIdParam) {
      targetConversation = tenantConversations.find((item) => Number(item.tenant_id) === Number(tenantIdParam)) || null
    }
    if (targetConversation && activeTenantConversation?.id !== targetConversation.id) {
      setActiveTenantConversation(targetConversation)
    }
  }, [activeTenantConversation?.id, conversationIdParam, tenantConversations, tenantIdParam])

  useEffect(() => {
    if (!directConversationIdParam || directConversations.length === 0) return
    const targetConversation = directConversations.find((item) => Number(item.id) === Number(directConversationIdParam)) || null
    if (targetConversation && activeDirectConversation?.id !== targetConversation.id) {
      setActiveDirectConversation(targetConversation)
    }
  }, [activeDirectConversation?.id, directConversationIdParam, directConversations])

  useEffect(() => {
    if (!echo || !adminId) return

    const channel = echo.private(`chat.admin.${adminId}`)
    channel.listen('.ChatMessageSent', (event: ChatMessageSentEvent) => {
      if (isDirectConversation(event.conversation)) {
        upsertDirectConversation(event.conversation)
        if (event.conversation.id === activeDirectConversation?.id) {
          appendDirectMessage(event.message)
          if (!messageIsMine(event.message, adminId)) {
            scheduleDirectRead(event.conversation.id)
          }
        }
      } else {
        upsertTenantConversation(event.conversation)
        if (event.conversation.id === activeTenantConversation?.id) {
          appendTenantMessage(event.message)
          if (messageSenderRole(event.message) !== ADMIN_ROLE) {
            scheduleTenantRead(event.conversation.id)
          }
        }
      }
    })
    channel.listen('.ChatConversationRead', (event: ChatConversationReadEvent) => {
      if (isDirectConversation(event.conversation)) {
        upsertDirectConversation(event.conversation)
      } else {
        upsertTenantConversation(event.conversation)
      }
    })

    return () => {
      channel.stopListening('.ChatMessageSent')
      channel.stopListening('.ChatConversationRead')
      if (markReadTimerRef.current) clearTimeout(markReadTimerRef.current)
    }
  }, [activeDirectConversation?.id, activeTenantConversation?.id, adminId, echo, upsertDirectConversation, upsertTenantConversation])

  useEffect(() => {
    const conversation = activeConversation
    if (!echo || !conversation) return

    const channel = echo.private(`chat.conversation.${conversation.id}`)

    channel.listen('.ChatMessageSent', (event: ChatMessageSentEvent) => {
      if (isDirectConversation(conversation)) {
        if (messageIsMine(event.message, adminId)) return
        upsertDirectConversation(event.conversation)
        appendDirectMessage(event.message)
        return
      }

      if (messageSenderRole(event.message) === ADMIN_ROLE) return
      upsertTenantConversation(event.conversation)
      appendTenantMessage(event.message)
    })
    channel.listen('.ChatConversationRead', (event: ChatConversationReadEvent) => {
      if (isDirectConversation(event.conversation)) {
        upsertDirectConversation(event.conversation)
      } else {
        upsertTenantConversation(event.conversation)
      }
    })

    return () => {
      channel.stopListening('.ChatMessageSent')
      channel.stopListening('.ChatConversationRead')
    }
  }, [activeConversation?.id, adminId, echo, upsertDirectConversation, upsertTenantConversation])

  const appendTenantMessage = (message: ChatMessageResource) => {
    const container = scrollContainerRef.current
    const isNearBottom = container ? container.scrollHeight - container.scrollTop - container.clientHeight < NEAR_BOTTOM_THRESHOLD : false
    setTenantMessages((current) => current.some((item) => item.id === message.id) ? current : [...current.filter((item) => !item.optimistic), message])
    if (isNearBottom) scrollToBottomSoon()
  }

  const appendDirectMessage = (message: ChatMessageResource) => {
    const container = scrollContainerRef.current
    const isNearBottom = container ? container.scrollHeight - container.scrollTop - container.clientHeight < NEAR_BOTTOM_THRESHOLD : false
    setDirectMessages((current) => current.some((item) => item.id === message.id) ? current : [...current.filter((item) => !item.optimistic), message])
    if (isNearBottom) scrollToBottomSoon()
  }

  const scrollToBottomSoon = () => {
    window.requestAnimationFrame(() => {
      if (scrollContainerRef.current) {
        scrollContainerRef.current.scrollTop = scrollContainerRef.current.scrollHeight
      }
    })
  }

  const scheduleTenantRead = (conversationId: number) => {
    if (markReadTimerRef.current) clearTimeout(markReadTimerRef.current)
    markReadTimerRef.current = setTimeout(() => {
      void markAdminChatRead(conversationId).then((response) => response.result && upsertTenantConversation(response.result)).catch(() => null)
    }, 500)
  }

  const scheduleDirectRead = (conversationId: number) => {
    if (markReadTimerRef.current) clearTimeout(markReadTimerRef.current)
    markReadTimerRef.current = setTimeout(() => {
      void markAdminDirectRead(conversationId).then((response) => response.result && upsertDirectConversation(response.result)).catch(() => null)
    }, 500)
  }

  const handleSendMessage = async () => {
    if (!activeConversation || (!input.trim() && selectedImages.length === 0)) return
    setIsSending(true)
    setErrorMessage(null)
    const body = input.trim()
    const images = selectedImages
    const optimisticMessage: UnifiedMessage = isDirectConversation(activeConversation)
      ? {
        id: Date.now(),
        conversation_id: activeConversation.id,
        sender_type: 'admin',
        sender_id: Number(adminId),
        sender_role: ADMIN_ROLE,
        sender_name: session?.admin?.full_name || session?.admin?.username || 'Bạn',
        sender_avatar_url: session?.admin?.avatar_url || null,
        body,
        attachments: imagePreviews,
        created_at: new Date().toISOString(),
        optimistic: true,
      }
      : {
        id: Date.now(),
        conversation_id: activeConversation.id,
        sender_type: 'admin',
        sender_id: Number(adminId),
        sender_role: ADMIN_ROLE,
        sender_name: session?.admin?.full_name || session?.admin?.username || 'Bạn',
        sender_avatar_url: session?.admin?.avatar_url || null,
        body,
        attachments: imagePreviews,
        created_at: new Date().toISOString(),
        optimistic: true,
      }

    if (isDirectConversation(activeConversation)) {
      setDirectMessages((current) => [...current, optimisticMessage as ChatMessageResource])
    } else {
      setTenantMessages((current) => [...current, optimisticMessage as ChatMessageResource])
    }
    setInput('')
    setSelectedImages([])
    scrollToBottomSoon()

    try {
      if (isDirectConversation(activeConversation)) {
        const response = await sendAdminDirectMessage(activeConversation.id, body, images)
        if (response.result) {
          setDirectMessages((current) => [...current.filter((item) => !item.optimistic), response.result!.message])
          upsertDirectConversation(response.result.conversation)
        }
      } else {
        const response = await sendAdminChatMessage(activeConversation.id, body, images)
        if (response.result) {
          setTenantMessages((current) => [...current.filter((item) => !item.optimistic), response.result!.message])
          upsertTenantConversation(response.result.conversation)
        }
      }
    } catch (error: any) {
      if (isDirectConversation(activeConversation)) {
        setDirectMessages((current) => current.filter((item) => !item.optimistic))
      } else {
        setTenantMessages((current) => current.filter((item) => !item.optimistic))
      }
      setErrorMessage(error?.message || 'Không thể gửi tin nhắn.')
      setInput(body)
      setSelectedImages(images)
    } finally {
      setIsSending(false)
    }
  }

  const handleMouseDown = (e: React.MouseEvent) => {
    if (scale > 1) {
      setIsDragging(true)
      setDragStart({ x: e.clientX - position.x, y: e.clientY - position.y })
    }
  }

  const handleMouseMove = (e: React.MouseEvent) => {
    if (isDragging && scale > 1) {
      setPosition({ x: e.clientX - dragStart.x, y: e.clientY - dragStart.y })
    }
  }

  const handleMouseUp = () => setIsDragging(false)

  const handleWheel = (e: React.WheelEvent) => {
    e.preventDefault()
    const delta = e.deltaY > 0 ? -0.2 : 0.2
    setScale((prev) => Math.min(Math.max(0.5, prev + delta), 5))
  }

  const partnerTitle = activeConversation ? getConversationTitle(activeConversation, adminId) : ''
  const partnerSubtitle = activeConversation ? getConversationSubtitle(activeConversation, adminId) : ''
  const availableTabs = isSuperAdmin ? ['direct' as ChatTab] : ['tenants' as ChatTab, 'direct' as ChatTab]
  const totalUnread = activeTab === 'direct' ? directUnread : tenantUnread

  return (
    <section className="flex h-full min-h-0 overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/85 shadow-2xl shadow-[#6b3f1d]/10 backdrop-blur-xl">
      <div className="grid h-full min-h-0 w-full grid-cols-1 lg:grid-cols-[380px_minmax(0,1fr)]">
        <aside className="flex min-h-0 flex-col border-b border-[#3d2a18]/10 bg-[#24170d] text-[#fffaf1] lg:h-full lg:border-b-0 lg:border-r">
          <div className="shrink-0 space-y-4 p-5">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h1 className="mt-1 text-3xl font-black tracking-[-0.05em]">Đoạn chat</h1>
              </div>
              <div className="rounded-2xl bg-[#f3c56b] px-3 py-2 text-sm font-black text-[#24170d]">{totalUnread}</div>
            </div>

            {availableTabs.length > 1 && (
              <div className="mx-auto flex w-fit items-center rounded-full border border-[#3d2a18]/10 bg-[#fffaf1] p-1 shadow-lg shadow-black/15">
                <button
                  type="button"
                  onClick={() => setActiveTab('tenants')}
                  className={cn(
                    'flex h-10 w-24 items-center justify-center rounded-full text-sm font-black transition',
                    activeTab === 'tenants' ? 'bg-[#f3c56b] text-[#24170d] shadow-sm' : 'text-[#8b5e34] hover:bg-[#f3c56b]/20',
                  )}
                >
                  Tenant
                </button>
                <div className="h-6 w-px bg-[#3d2a18]/10" />
                <button
                  type="button"
                  onClick={() => setActiveTab('direct')}
                  className={cn(
                    'flex h-10 w-24 items-center justify-center rounded-full text-sm font-black transition',
                    activeTab === 'direct' ? 'bg-[#f3c56b] text-[#24170d] shadow-sm' : 'text-[#8b5e34] hover:bg-[#f3c56b]/20',
                  )}
                >
                  Admin
                </button>
              </div>
            )}

            <div className="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/10 px-3 py-2">
              <Search className="h-4 w-4 text-[#f3c56b]" />
              <input
                value={keyword}
                onChange={(event) => setKeyword(event.target.value)}
                placeholder={activeTab === 'direct' ? 'Tìm quản lý tòa nhà...' : 'Tìm phòng hoặc khách thuê...'}
                className="min-h-10 flex-1 bg-transparent text-sm font-bold text-white outline-none placeholder:text-white/45"
              />
            </div>

            <div className="flex gap-2">
              <button type="button" onClick={() => setShowUnreadOnly(false)} className={cn('min-h-10 rounded-full px-4 text-sm font-black transition', !showUnreadOnly ? 'bg-[#f3c56b] text-[#24170d]' : 'bg-white/10 text-white/70 hover:bg-white/15')}>Tất cả</button>
              <button type="button" onClick={() => setShowUnreadOnly(true)} className={cn('min-h-10 rounded-full px-4 text-sm font-black transition', showUnreadOnly ? 'bg-[#f3c56b] text-[#24170d]' : 'bg-white/10 text-white/70 hover:bg-white/15')}>Chưa đọc</button>
            </div>
          </div>

          <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 pb-4 custom-scrollbar">
            {isLoadingConversations ? (
              <div className="space-y-3 p-3">
                {Array.from({ length: 5 }).map((_, index) => <div key={index} className="h-20 animate-pulse rounded-3xl bg-white/10" />)}
              </div>
            ) : visibleConversations.length === 0 ? (
              <div className="m-3 rounded-3xl border border-white/10 bg-white/8 p-6 text-center text-sm font-bold text-white/65">Chưa có đoạn chat nào.</div>
            ) : visibleConversations.map((conversation) => {
              const direct = isDirectConversation(conversation)
              const selected = activeConversation?.id === conversation.id && (activeTab === 'direct') === direct
              const unread = direct ? getDirectUnread(conversation, adminId) : Number(conversation.admin_unread_count || 0)
              const title = direct ? getConversationTitle(conversation, adminId) : `Phòng ${conversation.room_number || '—'} · ${conversation.tenant_name || 'Khách thuê'}`
              const avatarText = direct
                ? (Number(conversation.super_admin_id) === Number(adminId)
                  ? (conversation.manager_name || conversation.manager_username || 'QL')
                  : (conversation.super_admin_name || conversation.super_admin_username || 'SA')).slice(0, 2).toUpperCase()
                : (conversation.room_number || 'P?')
              return (
                <button
                  key={`${direct ? 'direct' : 'tenant'}-${conversation.id}`}
                  type="button"
                  onClick={() => {
                    if (direct) {
                      setActiveDirectConversation(conversation)
                      setActiveTab('direct')
                    } else {
                      setActiveTenantConversation(conversation)
                      setActiveTab('tenants')
                    }
                  }}
                  className={cn('mb-2 flex w-full items-center gap-3 rounded-3xl p-3 text-left transition', selected ? 'bg-[#fffaf1] text-[#24170d] shadow-xl shadow-black/20' : 'text-white hover:bg-white/10')}
                >
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#f3c56b] text-sm font-black text-[#24170d] shadow-lg">
                    {avatarText}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                      <p className="truncate text-sm font-black">{title}</p>
                      {unread > 0 && <span className="rounded-full bg-[#006dff] px-2 py-0.5 text-[10px] font-black text-white">{unread}</span>}
                    </div>
                    <p className="mt-1 truncate text-xs font-bold opacity-70">{conversation.last_message?.body || (direct ? getConversationSubtitle(conversation, adminId) : conversation.building_name) || 'Bắt đầu trò chuyện'}</p>
                  </div>
                </button>
              )
            })}
          </div>
        </aside>

        <main className="flex min-h-0 flex-col overflow-hidden bg-[radial-gradient(circle_at_20%_0%,rgba(243,197,107,0.24),transparent_32%),linear-gradient(180deg,#fffaf1,#f4efe6)] lg:h-full">
          {activeConversation ? (
            <>
              <header className="flex shrink-0 items-center justify-between gap-4 border-b border-[#3d2a18]/10 bg-white/55 p-5 backdrop-blur-md">
                <div className="flex min-w-0 items-center gap-3">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#24170d] text-sm font-black text-[#f3c56b]">
                    {isDirectConversation(activeConversation)
                      ? (Number(activeConversation.super_admin_id) === Number(adminId)
                        ? (activeConversation.manager_name || activeConversation.manager_username || 'QL').slice(0, 2).toUpperCase()
                        : (activeConversation.super_admin_name || activeConversation.super_admin_username || 'SA').slice(0, 2).toUpperCase())
                      : activeConversation.room_number || 'P?'}
                  </div>
                  <div className="min-w-0">
                    <h2 className="truncate text-xl font-black tracking-[-0.03em] text-[#24170d]">
                      {isDirectConversation(activeConversation) ? partnerTitle : `Phòng ${activeConversation.room_number || '—'} - ${activeConversation.tenant_name || 'Khách thuê'}`}
                    </h2>
                    <p className="truncate text-xs font-black uppercase tracking-[0.16em] text-[#8b5e34]">
                      {isDirectConversation(activeConversation) ? partnerSubtitle : `${activeConversation.building_name || 'Tòa nhà'} · ${activeConversation.tenant_phone || 'Chưa có SĐT'}`}
                    </p>
                  </div>
                </div>
              </header>

              <div
                ref={scrollContainerRef}
                onScroll={handleScroll}
                className="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 py-5 custom-scrollbar"
              >
                {isLoadingMessages ? (
                  <div className="flex h-full items-center justify-center"><Loader2 className="h-7 w-7 animate-spin text-[#8b5e34]" /></div>
                ) : messages.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-center">
                    <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-white/60 p-8 shadow-xl shadow-[#6b3f1d]/8">
                      <MessageCircle className="mx-auto h-12 w-12 text-[#8b5e34]" />
                      <p className="mt-4 text-xl font-black tracking-[-0.04em]">Bắt đầu cuộc trò chuyện</p>
                      <p className="mt-2 text-sm font-bold text-[#6f6254]">Tin nhắn chỉ hiển thị với hai bên trong đoạn chat này.</p>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {isLoadingMore && <div className="flex justify-center py-2"><Loader2 className="h-5 w-5 animate-spin text-[#8b5e34]" /></div>}
                    {messages.map((message, index) => {
                      const previous = messages[index - 1]
                      const currentDividerLabel = getChatDividerLabel(message.created_at)
                      const previousDividerLabel = previous ? getChatDividerLabel(previous.created_at) : null
                      const dividerLabel = currentDividerLabel && currentDividerLabel !== previousDividerLabel ? currentDividerLabel : ''
                      const mine = messageIsMine(message, adminId)
                      return (
                        <div key={`${message.id}-${message.optimistic ? 'optimistic' : 'saved'}`}>
                          {dividerLabel && <div className="my-4 flex justify-center"><span className="rounded-full bg-[#3d2a18]/8 px-3 py-1 text-[10px] font-black uppercase tracking-[0.14em] text-[#6f6254]">{dividerLabel}</span></div>}
                          <div className={cn('flex', mine ? 'justify-end' : 'justify-start')}>
                            <div className={cn('max-w-[78%]', mine ? 'items-end' : 'items-start')}>
                              <div
                                className={cn(
                                  'rounded-[1.35rem] px-4 py-2.5 shadow-sm',
                                  mine ? 'rounded-br-md bg-[#8b5e34] text-white' : 'rounded-bl-md border border-[#3d2a18]/10 bg-white text-[#24170d]',
                                  !message.body && message.attachments?.length ? 'bg-transparent p-0 shadow-none' : '',
                                )}
                              >
                                {message.body && <p className="w-full whitespace-pre-wrap break-words text-left text-sm font-bold leading-6">{message.body}</p>}
                                {message.attachments && message.attachments.length > 0 && renderAttachments(message.attachments, setActiveImageUrls, setActiveImageIndex)}
                              </div>
                              <p className="mt-1 px-1.5 text-[10px] font-black uppercase tracking-[0.14em] text-[#8b5e34]/70">{message.optimistic ? 'Đang gửi...' : formatTimeOnly(message.created_at)}</p>
                            </div>
                          </div>
                        </div>
                      )
                    })}
                    <div ref={messagesEndRef} />
                  </div>
                )}
              </div>

              {errorMessage && <div className="mx-5 mb-3 shrink-0 rounded-2xl border border-rose-900/10 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{errorMessage}</div>}
              <ChatComposer
                fileInputRef={fileInputRef}
                imagePreviews={imagePreviews}
                input={input}
                isSending={isSending}
                selectedImages={selectedImages}
                setInput={setInput}
                setSelectedImages={setSelectedImages}
                textareaRef={textareaRef}
                onSend={() => { void handleSendMessage() }}
              />
            </>
          ) : (
            <div className="flex flex-1 items-center justify-center p-8 text-center">
              <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-white/65 p-10 shadow-xl shadow-[#6b3f1d]/8">
                <MessageCircle className="mx-auto h-12 w-12 text-[#8b5e34]" />
                <p className="mt-4 text-2xl font-black tracking-[-0.04em] text-[#24170d]">Chọn một đoạn chat</p>
              </div>
            </div>
          )}
        </main>
      </div>

      {activeImageUrls && activeImageUrls.length > 0 && createPortal(
        <ImageLightbox
          activeImageIndex={activeImageIndex}
          activeImageUrls={activeImageUrls}
          handleMouseDown={handleMouseDown}
          handleMouseMove={handleMouseMove}
          handleMouseUp={handleMouseUp}
          handleWheel={handleWheel}
          isDragging={isDragging}
          position={position}
          scale={scale}
          setActiveImageIndex={setActiveImageIndex}
          setActiveImageUrls={setActiveImageUrls}
          setPosition={setPosition}
          setScale={setScale}
        />,
        document.body
      )}
    </section>
  )
}

function getConversationTitle(conversation: UnifiedConversation, currentAdminId?: number) {
  if (isDirectConversation(conversation)) {
    if (Number(conversation.super_admin_id) === Number(currentAdminId)) {
      return conversation.manager_name || conversation.manager_username || `Quản lý #${conversation.manager_admin_id}`
    }
    return conversation.super_admin_name || conversation.super_admin_username || `Quản lý #${conversation.super_admin_id}`
  }

  return conversation.tenant_name || `Khách thuê #${conversation.tenant_id}`
}

function getConversationSubtitle(conversation: UnifiedConversation, currentAdminId?: number) {
  if (isDirectConversation(conversation)) {
    if (Number(conversation.super_admin_id) === Number(currentAdminId)) {
      const buildingNames = conversation.manager_building_names?.length ? conversation.manager_building_names.join(', ') : `${conversation.manager_buildings_count || 0} tòa nhà`
      return `${conversation.manager_phone || conversation.manager_email || 'Chưa có liên hệ'} • ${buildingNames}`
    }
    return 'Ban Quản trị / Quản lý StayHub'
  }

  return `${conversation.building_name || 'Tòa nhà'} • Phòng ${conversation.room_number || conversation.room_id}`
}

function renderAttachments(attachments: string[], setActiveImageUrls: (urls: string[] | null) => void, setActiveImageIndex: (index: number) => void) {
  if (attachments.length === 1) {
    return (
      <img
        src={attachments[0]}
        alt="Attachment"
        onClick={() => { setActiveImageUrls(attachments); setActiveImageIndex(0) }}
        className="mt-2 block max-h-[420px] max-w-full cursor-pointer rounded-xl border border-[#3d2a18]/15 bg-black/5 transition hover:opacity-95 sm:max-h-[500px]"
      />
    )
  }

  return (
    <div className="mt-2 grid w-full max-w-md grid-cols-2 gap-2">
      {attachments.map((url, index) => (
        <img
          key={`${url}-${index}`}
          src={url}
          alt="Attachment"
          onClick={() => { setActiveImageUrls(attachments); setActiveImageIndex(index) }}
          className="h-32 w-full cursor-pointer rounded-xl border border-[#3d2a18]/15 bg-black/5 object-cover transition hover:opacity-95 sm:h-40"
        />
      ))}
    </div>
  )
}

function ChatComposer({
  fileInputRef,
  imagePreviews,
  input,
  isSending,
  onSend,
  selectedImages,
  setInput,
  setSelectedImages,
  textareaRef,
}: {
  fileInputRef: React.RefObject<HTMLInputElement | null>
  imagePreviews: string[]
  input: string
  isSending: boolean
  onSend: () => void
  selectedImages: File[]
  setInput: React.Dispatch<React.SetStateAction<string>>
  setSelectedImages: React.Dispatch<React.SetStateAction<File[]>>
  textareaRef: React.RefObject<HTMLTextAreaElement | null>
}) {
  return (
    <form className="shrink-0 border-t border-[#3d2a18]/10 bg-white/70 p-3 backdrop-blur-md" onSubmit={(event) => { event.preventDefault(); onSend() }}>
      {imagePreviews.length > 0 && (
        <div className="mb-3 flex gap-3 overflow-x-auto rounded-2xl border border-[#3d2a18]/10 bg-[#fdfbf7] p-2.5 shadow-inner">
          {imagePreviews.map((url, index) => (
            <div key={url} className="group relative h-16 w-16 shrink-0">
              <img src={url} alt="Preview" className="h-full w-full rounded-xl border border-[#3d2a18]/10 object-cover shadow-sm transition duration-200 group-hover:scale-105" />
              <button type="button" onClick={() => setSelectedImages((prev) => prev.filter((_, itemIndex) => itemIndex !== index))} className="absolute -right-1.5 -top-1.5 rounded-full bg-[#24170d]/90 p-1 text-white shadow-md transition duration-200 hover:bg-[#8b5e34]">
                <X className="h-3 w-3" />
              </button>
            </div>
          ))}
        </div>
      )}

      <div className="flex items-center gap-1 rounded-3xl bg-[#f0f2f5] px-2 py-0.5">
        <input
          type="file"
          multiple
          accept="image/jpeg,image/png,image/jpg,image/webp"
          className="hidden"
          ref={fileInputRef}
          onChange={(event) => {
            if (event.target.files) {
              const filesArray = Array.from(event.target.files)
              setSelectedImages((prev) => [...prev, ...filesArray].slice(0, 5))
              event.target.value = ''
            }
          }}
        />
        <button type="button" onClick={() => fileInputRef.current?.click()} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#8b5e34] transition hover:bg-[#8b5e34]/10">
          <ImageIcon className="h-5.5 w-5.5" />
        </button>
        <textarea
          ref={textareaRef}
          rows={1}
          value={input}
          onChange={(event) => setInput(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter' && !event.shiftKey) {
              event.preventDefault()
              onSend()
            }
          }}
          placeholder="Aa"
          spellCheck={false}
          className="max-h-32 min-h-5 flex-1 resize-none self-center bg-transparent px-2 py-0.5 text-[15px] font-medium text-[#24170d] outline-none placeholder:text-gray-500"
        />
        <button type="submit" disabled={(!input.trim() && selectedImages.length === 0) || isSending} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#8b5e34] transition hover:bg-[#8b5e34]/10 disabled:cursor-not-allowed disabled:opacity-45">
          <Send className="h-5.5 w-5.5" />
        </button>
      </div>
    </form>
  )
}

function ImageLightbox({
  activeImageIndex,
  activeImageUrls,
  handleMouseDown,
  handleMouseMove,
  handleMouseUp,
  handleWheel,
  isDragging,
  position,
  scale,
  setActiveImageIndex,
  setActiveImageUrls,
  setPosition,
  setScale,
}: {
  activeImageIndex: number
  activeImageUrls: string[]
  handleMouseDown: (event: React.MouseEvent) => void
  handleMouseMove: (event: React.MouseEvent) => void
  handleMouseUp: () => void
  handleWheel: (event: React.WheelEvent) => void
  isDragging: boolean
  position: { x: number; y: number }
  scale: number
  setActiveImageIndex: React.Dispatch<React.SetStateAction<number>>
  setActiveImageUrls: React.Dispatch<React.SetStateAction<string[] | null>>
  setPosition: React.Dispatch<React.SetStateAction<{ x: number; y: number }>>
  setScale: React.Dispatch<React.SetStateAction<number>>
}) {
  return (
    <div className="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-black/95 backdrop-blur-md" onWheel={handleWheel}>
      <div className="absolute right-6 top-6 z-50 flex items-center gap-4" onClick={(event) => event.stopPropagation()}>
        <div className="flex select-none items-center gap-1.5 rounded-full border border-white/10 bg-white/10 px-4 py-1.5 text-xs font-semibold text-white backdrop-blur-md">
          <button type="button" onClick={() => setScale((prev) => Math.max(0.5, prev - 0.2))} className="p-1 transition hover:text-[#8b5e34]"><ZoomOut className="h-4 w-4 text-white" /></button>
          <span className="w-12 text-center text-white">{Math.round(scale * 100)}%</span>
          <button type="button" onClick={() => setScale((prev) => Math.min(5, prev + 0.2))} className="p-1 transition hover:text-[#8b5e34]"><ZoomIn className="h-4 w-4 text-white" /></button>
          <button type="button" onClick={() => { setScale(1); setPosition({ x: 0, y: 0 }) }} className="ml-2 border-l border-white/20 pl-2 text-[10px] font-bold text-white transition hover:text-[#8b5e34]">Reset</button>
        </div>
        <button type="button" onClick={() => setActiveImageUrls(null)} className="flex h-10 w-10 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"><X className="h-5 w-5" /></button>
      </div>

      {activeImageUrls.length > 1 && activeImageIndex > 0 && (
        <button type="button" onClick={(event) => { event.stopPropagation(); setActiveImageIndex((prev) => prev - 1) }} className="absolute left-6 top-1/2 z-[10000] flex h-12 w-12 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-white/5 bg-white/10 text-white transition hover:bg-white/20"><ChevronLeft className="h-6 w-6" /></button>
      )}
      {activeImageUrls.length > 1 && activeImageIndex < activeImageUrls.length - 1 && (
        <button type="button" onClick={(event) => { event.stopPropagation(); setActiveImageIndex((prev) => prev + 1) }} className="absolute right-6 top-1/2 z-[10000] flex h-12 w-12 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border border-white/5 bg-white/10 text-white transition hover:bg-white/20"><ChevronRight className="h-6 w-6" /></button>
      )}

      <div className="flex h-full w-full items-center justify-center overflow-hidden" onClick={() => setActiveImageUrls(null)}>
        <img
          src={activeImageUrls[activeImageIndex]}
          alt="Enlarged view"
          onClick={(event) => event.stopPropagation()}
          style={{
            transform: `translate(${position.x}px, ${position.y}px) scale(${scale})`,
            transition: isDragging ? 'none' : 'transform 0.1s ease-out',
          }}
          onMouseDown={handleMouseDown}
          onMouseMove={handleMouseMove}
          onMouseUp={handleMouseUp}
          onMouseLeave={handleMouseUp}
          className={cn('max-h-[90vh] max-w-[90vw] select-none rounded-lg object-contain shadow-2xl transition-all duration-300', scale > 1 ? 'cursor-grab active:cursor-grabbing' : 'cursor-default')}
        />
      </div>
    </div>
  )
}

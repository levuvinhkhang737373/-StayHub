import { useCallback, useEffect, useRef, useState } from 'react'
import { Image as ImageIcon, MessageCircle, Minus, Send, X } from 'lucide-react'
import { AnimatePresence, motion } from 'motion/react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatTimeOnly, getChatDividerLabel } from '../../../../shared/lib/utils/format'
import { useTenantSocket } from '../../../../shared/lib/socket/socket-context'
import {
  fetchTenantChatMessages,
  markTenantChatRead,
  sendTenantChatMessage,
} from '../../../shared/chat/services/chat.service'
import type {
  ChatConversationReadEvent,
  ChatConversationResource,
  ChatMessageResource,
  ChatMessageSentEvent,
} from '../../../shared/chat/types/chat.types'

const TENANT_ROLE = 1

export function TenantChatWidget() {
  const { echo } = useTenantSocket()
  const [isOpen, setIsOpen] = useState(false)
  const [conversation, setConversation] = useState<ChatConversationResource | null>(null)
  const [messages, setMessages] = useState<ChatMessageResource[]>([])
  const [input, setInput] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [isSending, setIsSending] = useState(false)
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const [toastMessage, setToastMessage] = useState<string | null>(null)
  const [selectedImages, setSelectedImages] = useState<File[]>([])
  const [imagePreviews, setImagePreviews] = useState<string[]>([])

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
  const messagesEndRef = useRef<HTMLDivElement | null>(null)
  const fileInputRef = useRef<HTMLInputElement | null>(null)

  const showChatToast = useCallback((message: string) => {
    setToastMessage(message)
    window.setTimeout(() => setToastMessage(null), 5000)
  }, [])

  const loadMessages = useCallback(async () => {
    setIsLoading(true)
    setErrorMessage(null)
    try {
      const response = await fetchTenantChatMessages({ per_page: 50 })
      setConversation(response.result?.conversation || null)
      setMessages(response.result?.data || [])
      if (response.result?.conversation?.tenant_unread_count) {
        const readResponse = await markTenantChatRead()
        if (readResponse.result) {
          setConversation(readResponse.result)
        }
      }
    } catch (error: any) {
      setErrorMessage(error?.message || 'Không thể tải đoạn chat.')
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    void loadMessages()
  }, [loadMessages])

  useEffect(() => {
    if (isOpen && conversation?.tenant_unread_count) {
      void markTenantChatRead().then((response) => response.result && setConversation(response.result)).catch(() => null)
    }
  }, [conversation?.id, conversation?.tenant_unread_count, isOpen])

  useEffect(() => {
    if (isOpen) {
      messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' })
    }
  }, [isOpen, messages.length])

  useEffect(() => {
    if (!echo || !conversation) return

    const conversationChannel = echo.private(`chat.conversation.${conversation.id}`)
    conversationChannel.listen('.ChatMessageSent', (event: ChatMessageSentEvent) => {
      setConversation(event.conversation)
      setMessages((current) => current.some((item) => item.id === event.message.id) ? current : [...current.filter((item) => !item.optimistic), event.message])
      if (!isOpen && event.message.sender_role !== TENANT_ROLE) {
        showChatToast(event.message.body || 'Bạn có tin nhắn mới từ quản lý.')
      }
      if (isOpen) {
        void markTenantChatRead().then((response) => response.result && setConversation(response.result)).catch(() => null)
      }
    })
    conversationChannel.listen('.ChatConversationRead', (event: ChatConversationReadEvent) => {
      setConversation(event.conversation)
    })

    const tenantChannel = echo.private(`chat.tenant.${conversation.tenant_id}`)
    tenantChannel.listen('.ChatMessageSent', (event: ChatMessageSentEvent) => {
      setConversation(event.conversation)
    })
    tenantChannel.listen('.NotificationSent', (event: any) => {
      const notification = event.notification
      if (Number(notification?.notification_type) === 6) {
        setConversation((current) => current ? { ...current, tenant_unread_count: Math.max(current.tenant_unread_count, 1) } : current)
      }
    })

    return () => {
      conversationChannel.stopListening('.ChatMessageSent')
      conversationChannel.stopListening('.ChatConversationRead')
      tenantChannel.stopListening('.ChatMessageSent')
      tenantChannel.stopListening('.NotificationSent')
    }
  }, [conversation?.id, conversation?.tenant_id, echo, isOpen, showChatToast])

  async function handleSendMessage() {
    const body = input.trim()
    const imagesToSend = [...selectedImages]
    if ((!body && imagesToSend.length === 0) || isSending) return

    const optimisticImages = imagesToSend.map(file => URL.createObjectURL(file))
    const optimisticMessage: ChatMessageResource = {
      id: Date.now() * -1,
      conversation_id: conversation?.id || 0,
      sender_type: 'tenant',
      sender_id: conversation?.tenant_id || 0,
      sender_role: TENANT_ROLE,
      sender_role_label: 'Khách thuê',
      body,
      attachments: optimisticImages,
      created_at: new Date().toISOString(),
      optimistic: true,
    }

    setInput('')
    setSelectedImages([])
    setMessages((current) => [...current, optimisticMessage])
    setIsSending(true)
    setErrorMessage(null)
    try {
      const response = await sendTenantChatMessage(body, imagesToSend)
      if (response.result) {
        setConversation(response.result.conversation)
        setMessages((current) => [...current.filter((item) => item.id !== optimisticMessage.id), response.result.message])
      }
    } catch (error: any) {
      setMessages((current) => current.filter((item) => item.id !== optimisticMessage.id))
      setInput(body)
      setSelectedImages(imagesToSend)
      setErrorMessage(error?.message || 'Không thể gửi tin nhắn.')
    } finally {
      setIsSending(false)
      optimisticImages.forEach(url => URL.revokeObjectURL(url))
    }
  }

  const unreadCount = conversation?.tenant_unread_count || 0

  return (
    <div className="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-3">
      <AnimatePresence>
        {toastMessage && !isOpen && (
          <motion.button
            type="button"
            initial={{ opacity: 0, y: 16, scale: 0.96 }}
            animate={{ opacity: 1, y: 0, scale: 1 }}
            exit={{ opacity: 0, y: 12, scale: 0.96 }}
            onClick={() => setIsOpen(true)}
            className="w-[min(calc(100vw-2rem),360px)] rounded-[1.35rem] border border-[#0f766e]/15 bg-white p-4 text-left shadow-2xl shadow-[#0f766e]/20"
          >
            <p className="text-[10px] font-black uppercase tracking-[0.18em] text-[#0f766e]">Tin nhắn mới</p>
            <p className="mt-1 line-clamp-2 text-sm font-bold leading-5 text-[#24170d]">{toastMessage}</p>
          </motion.button>
        )}
      </AnimatePresence>
      {isOpen && (
        <div className="w-[min(calc(100vw-2rem),390px)] overflow-hidden rounded-[1.8rem] border border-[#3d2a18]/10 bg-[#fffaf1] shadow-2xl shadow-[#24170d]/25">
          <header className="flex items-center justify-between bg-[#0f766e] p-4 text-white">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#0f766e]">
                <MessageCircle className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-black">Chat với quản lý</p>
                <p className="text-[11px] font-bold text-white/75">{conversation?.manager_name || 'StayHub'}</p>
              </div>
            </div>
            <div className="flex items-center gap-1">
              <button type="button" onClick={() => setIsOpen(false)} className="flex min-h-9 min-w-9 items-center justify-center rounded-full text-white/80 transition hover:bg-white/10" aria-label="Thu nhỏ chat">
                <Minus className="h-4 w-4" />
              </button>
            </div>
          </header>

          <div className="h-[420px] overflow-y-auto bg-[radial-gradient(circle_at_10%_0%,rgba(15,118,110,0.12),transparent_34%),linear-gradient(180deg,#fffaf1,#f4efe6)] p-4">
            {isLoading ? (
              <div className="space-y-3">
                {Array.from({ length: 5 }).map((_, index) => <div key={index} className={cn('h-12 animate-pulse rounded-3xl bg-[#3d2a18]/10', index % 2 ? 'ml-auto w-2/3' : 'w-3/4')} />)}
              </div>
            ) : messages.length === 0 ? (
              <div className="flex h-full items-center justify-center text-center">
                <div>
                  <MessageCircle className="mx-auto h-10 w-10 text-[#0f766e]" />
                  <p className="mt-3 text-base font-black text-[#24170d]">Bạn cần hỗ trợ?</p>
                  <p className="mt-1 text-sm font-bold text-[#6f6254]">Nhắn trực tiếp cho quản lý tòa nhà của bạn.</p>
                </div>
              </div>
            ) : (
              <div className="space-y-3">
                {messages.map((message, index) => {
                  const isMine = message.sender_role === TENANT_ROLE
                  const prevMessage = index > 0 ? messages[index - 1] : null
                  const currentDividerLabel = getChatDividerLabel(message.created_at)
                  const prevDividerLabel = prevMessage ? getChatDividerLabel(prevMessage.created_at) : null
                  const showDateDivider = !prevMessage || currentDividerLabel !== prevDividerLabel

                  return (
                    <div key={message.id} className="space-y-3">
                      {showDateDivider && (
                        <div className="my-5 flex items-center justify-center">
                          <div className="h-[1px] flex-1 bg-[#0f766e]/15" />
                          <span className="mx-3 text-[10px] font-black uppercase tracking-[0.18em] text-[#0f766e]/70 bg-transparent px-2">
                            {currentDividerLabel}
                          </span>
                          <div className="h-[1px] flex-1 bg-[#0f766e]/15" />
                        </div>
                      )}
                      <div className={cn('flex', isMine ? 'justify-end' : 'justify-start')}>
                        <div className={cn('max-w-[82%] rounded-[1.35rem] px-4 py-3 shadow-sm', isMine ? 'bg-[#0f766e] text-white' : 'border border-[#3d2a18]/10 bg-white text-[#24170d]')}>
                          {message.body && <p className="whitespace-pre-wrap text-sm font-bold leading-6">{message.body}</p>}
                          {message.attachments && message.attachments.length > 0 && (
                            <div className={cn("mt-2 grid gap-1", message.attachments.length > 1 ? "grid-cols-2" : "grid-cols-1")}>
                              {message.attachments.map((url, i) => (
                                <img key={i} src={url} alt="Attachment" className="h-24 w-full rounded-xl object-cover border border-white/20 bg-black/5" />
                              ))}
                            </div>
                          )}
                          <p className={cn('mt-1 text-[10px] font-black uppercase tracking-[0.13em]', isMine ? 'text-white/55' : 'text-[#8b5e34]/70')}>{message.optimistic ? 'Đang gửi...' : formatTimeOnly(message.created_at)}</p>
                        </div>
                      </div>
                    </div>
                  )
                })}
                <div ref={messagesEndRef} />
              </div>
            )}
          </div>

          {errorMessage && <div className="border-t border-rose-900/10 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700">{errorMessage}</div>}

          <form className="border-t border-[#3d2a18]/10 bg-white p-2.5" onSubmit={(event) => { event.preventDefault(); void handleSendMessage() }}>
            {imagePreviews.length > 0 && (
              <div className="mb-3 flex gap-3 overflow-x-auto rounded-2xl border border-[#0f766e]/15 bg-[#f4fbf9] p-2.5 shadow-inner">
                {imagePreviews.map((url, index) => (
                  <div key={index} className="relative h-16 w-16 shrink-0 group">
                    <img src={url} alt="Preview" className="h-full w-full rounded-xl object-cover border border-[#0f766e]/10 shadow-sm transition duration-200 group-hover:scale-105" />
                    <button type="button" onClick={() => setSelectedImages(prev => prev.filter((_, i) => i !== index))} className="absolute -right-1.5 -top-1.5 rounded-full bg-[#24170d]/90 p-1 text-white shadow-md hover:bg-[#0f766e] transition duration-200">
                      <X className="h-3 w-3" />
                    </button>
                  </div>
                ))}
              </div>
            )}
            <div className="flex items-center gap-1 rounded-3xl bg-[#f0f2f5] px-2 py-0.5">
              <input type="file" multiple accept="image/jpeg,image/png,image/jpg,image/webp" className="hidden" ref={fileInputRef} onChange={(e) => {
                if (e.target.files) {
                  const filesArray = Array.from(e.target.files);
                  setSelectedImages(prev => [...prev, ...filesArray].slice(0, 5));
                  e.target.value = ''
                }
              }} />
              <button type="button" onClick={() => fileInputRef.current?.click()} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#0f766e] transition hover:bg-[#0f766e]/10">
                <ImageIcon className="h-5.5 w-5.5" />
              </button>
              
              <textarea
                rows={1}
                value={input}
                onChange={(event) => setInput(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault()
                    void handleSendMessage()
                  }
                }}
                placeholder="Aa"
                className="max-h-24 min-h-5 flex-1 resize-none bg-transparent px-2 py-0.5 text-[15px] font-medium text-[#24170d] outline-none placeholder:text-gray-500 self-center"
              />

              <button type="submit" disabled={(!input.trim() && selectedImages.length === 0) || isSending} className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-[#0f766e] transition hover:bg-[#0f766e]/10 disabled:cursor-not-allowed disabled:opacity-45" aria-label="Gửi tin nhắn">
                <Send className="h-5.5 w-5.5" />
              </button>
            </div>
          </form>
        </div>
      )}

      <button
        type="button"
        onClick={() => setIsOpen((value) => !value)}
        className="relative flex min-h-16 min-w-16 items-center justify-center rounded-full bg-[#00b65a] text-white shadow-2xl shadow-[#0f766e]/35 transition hover:scale-105 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-[#00b65a]/25 active:scale-95"
        aria-label={isOpen ? 'Đóng chat' : 'Mở chat'}
      >
        {isOpen ? <X className="h-7 w-7" /> : <MessageCircle className="h-7 w-7" />}
        {unreadCount > 0 && !isOpen && <span className="absolute -right-1 -top-1 flex h-6 min-w-6 items-center justify-center rounded-full bg-red-600 px-1 text-xs font-black text-white ring-2 ring-white">{unreadCount}</span>}
      </button>
    </div>
  )
}

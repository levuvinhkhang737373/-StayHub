import { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { MessageCircle, Search, Send, Users, Wifi, WifiOff } from 'lucide-react'
import { useAdminSocket } from '../../../../shared/lib/socket/socket-context'
import { useAdminSession } from '../../auth/hooks/use-admin-session'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatDateTime } from '../../../../shared/lib/utils/format'
import {
  fetchAdminChatConversations,
  fetchAdminChatMessages,
  markAdminChatRead,
  sendAdminChatMessage,
} from '../../../shared/chat/services/chat.service'
import type {
  ChatConversationReadEvent,
  ChatConversationResource,
  ChatMessageResource,
  ChatMessageSentEvent,
} from '../../../shared/chat/types/chat.types'

const ADMIN_ROLE = 2

export function AdminChatScreen() {
  const { echo } = useAdminSocket()
  const { session } = useAdminSession()
  const adminId = session?.admin?.id
  const [conversations, setConversations] = useState<ChatConversationResource[]>([])
  const [activeConversation, setActiveConversation] = useState<ChatConversationResource | null>(null)
  const [messages, setMessages] = useState<ChatMessageResource[]>([])
  const [keyword, setKeyword] = useState('')
  const [showUnreadOnly, setShowUnreadOnly] = useState(false)
  const [isLoadingConversations, setIsLoadingConversations] = useState(true)
  const [isLoadingMessages, setIsLoadingMessages] = useState(false)
  const [isSending, setIsSending] = useState(false)
  const [input, setInput] = useState('')
  const [errorMessage, setErrorMessage] = useState<string | null>(null)
  const messagesEndRef = useRef<HTMLDivElement | null>(null)

  const totalUnread = useMemo(() => conversations.reduce((total, item) => total + Number(item.admin_unread_count || 0), 0), [conversations])

  const upsertConversation = useCallback((conversation: ChatConversationResource) => {
    setConversations((current) => {
      const next = [conversation, ...current.filter((item) => item.id !== conversation.id)]
      return next.sort((left, right) => new Date(right.last_message_at || right.updated_at || 0).getTime() - new Date(left.last_message_at || left.updated_at || 0).getTime())
    })
    setActiveConversation((current) => current?.id === conversation.id ? { ...current, ...conversation } : current)
  }, [])

  const loadConversations = useCallback(async () => {
    setIsLoadingConversations(true)
    setErrorMessage(null)
    try {
      const response = await fetchAdminChatConversations({
        keyword: keyword || undefined,
        unread: showUnreadOnly || undefined,
        per_page: 50,
      })
      const data = response.result?.data || []
      setConversations(data)
      setActiveConversation((current) => current && data.some((item) => item.id === current.id) ? current : data[0] || null)
    } catch (error: any) {
      setErrorMessage(error?.message || 'Không thể tải danh sách đoạn chat.')
    } finally {
      setIsLoadingConversations(false)
    }
  }, [keyword, showUnreadOnly])

  const loadMessages = useCallback(async (conversation: ChatConversationResource) => {
    setIsLoadingMessages(true)
    setErrorMessage(null)
    try {
      const response = await fetchAdminChatMessages(conversation.id, { per_page: 50 })
      setMessages(response.result?.data || [])
      if (conversation.admin_unread_count > 0) {
        const readResponse = await markAdminChatRead(conversation.id)
        if (readResponse.result) {
          upsertConversation(readResponse.result)
        }
      }
    } catch (error: any) {
      setErrorMessage(error?.message || 'Không thể tải tin nhắn.')
    } finally {
      setIsLoadingMessages(false)
    }
  }, [upsertConversation])

  useEffect(() => {
    const timer = window.setTimeout(() => {
      void loadConversations()
    }, 250)

    return () => window.clearTimeout(timer)
  }, [loadConversations])

  useEffect(() => {
    if (activeConversation) {
      void loadMessages(activeConversation)
    } else {
      setMessages([])
    }
  }, [activeConversation?.id, loadMessages])

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' })
  }, [messages.length, activeConversation?.id])

  useEffect(() => {
    if (!echo || !adminId) return

    const channel = echo.private(`chat.admin.${adminId}`)
    channel.listen('.ChatMessageSent', (event: ChatMessageSentEvent) => {
      upsertConversation(event.conversation)
      if (event.conversation.id === activeConversation?.id) {
        setMessages((current) => current.some((item) => item.id === event.message.id) ? current : [...current.filter((item) => !item.optimistic), event.message])
        void markAdminChatRead(event.conversation.id).then((response) => response.result && upsertConversation(response.result)).catch(() => null)
      }
    })
    channel.listen('.ChatConversationRead', (event: ChatConversationReadEvent) => {
      upsertConversation(event.conversation)
    })

    return () => {
      channel.stopListening('.ChatMessageSent')
      channel.stopListening('.ChatConversationRead')
    }
  }, [activeConversation?.id, adminId, echo, upsertConversation])

  useEffect(() => {
    if (!echo || !activeConversation) return

    const channel = echo.private(`chat.conversation.${activeConversation.id}`)
    channel.listen('.ChatMessageSent', (event: ChatMessageSentEvent) => {
      upsertConversation(event.conversation)
      setMessages((current) => current.some((item) => item.id === event.message.id) ? current : [...current.filter((item) => !item.optimistic), event.message])
    })
    channel.listen('.ChatConversationRead', (event: ChatConversationReadEvent) => {
      upsertConversation(event.conversation)
    })

    return () => {
      channel.stopListening('.ChatMessageSent')
      channel.stopListening('.ChatConversationRead')
    }
  }, [activeConversation?.id, echo, upsertConversation])

  async function handleSendMessage() {
    const body = input.trim()
    if (!body || !activeConversation || isSending) return

    const optimisticMessage: ChatMessageResource = {
      id: Date.now() * -1,
      conversation_id: activeConversation.id,
      sender_type: 'admin',
      sender_id: Number(adminId || 0),
      sender_role: ADMIN_ROLE,
      sender_role_label: 'Quản lý',
      sender_name: session?.admin?.full_name || 'Quản lý',
      body,
      created_at: new Date().toISOString(),
      optimistic: true,
    }

    setInput('')
    setMessages((current) => [...current, optimisticMessage])
    setIsSending(true)
    try {
      const response = await sendAdminChatMessage(activeConversation.id, body)
      if (response.result) {
        setMessages((current) => [...current.filter((item) => item.id !== optimisticMessage.id), response.result.message])
        upsertConversation(response.result.conversation)
      }
    } catch (error: any) {
      setMessages((current) => current.filter((item) => item.id !== optimisticMessage.id))
      setErrorMessage(error?.message || 'Không thể gửi tin nhắn.')
      setInput(body)
    } finally {
      setIsSending(false)
    }
  }

  return (
    <section className="min-h-[calc(100vh-8rem)] overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/85 shadow-2xl shadow-[#6b3f1d]/10 backdrop-blur-xl">
      <div className="grid min-h-[calc(100vh-8rem)] grid-cols-1 lg:grid-cols-[380px_minmax(0,1fr)]">
        <aside className="border-b border-[#3d2a18]/10 bg-[#24170d] text-[#fffaf1] lg:border-b-0 lg:border-r">
          <div className="space-y-4 p-5">
            <div className="flex items-center justify-between gap-3">
              <div>
                <h1 className="mt-1 text-3xl font-black tracking-[-0.05em]">Đoạn chat</h1>
              </div>
              <div className="rounded-2xl bg-[#f3c56b] px-3 py-2 text-sm font-black text-[#24170d]">{totalUnread}</div>
            </div>

            <div className="flex items-center gap-2 rounded-2xl border border-white/10 bg-white/10 px-3 py-2">
              <Search className="h-4 w-4 text-[#f3c56b]" />
              <input
                value={keyword}
                onChange={(event) => setKeyword(event.target.value)}
                placeholder="Tìm phòng hoặc khách thuê..."
                className="min-h-10 flex-1 bg-transparent text-sm font-bold text-white outline-none placeholder:text-white/45"
              />
            </div>

            <div className="flex gap-2">
              <button type="button" onClick={() => setShowUnreadOnly(false)} className={cn('min-h-10 rounded-full px-4 text-sm font-black transition', !showUnreadOnly ? 'bg-[#f3c56b] text-[#24170d]' : 'bg-white/10 text-white/70 hover:bg-white/15')}>Tất cả</button>
              <button type="button" onClick={() => setShowUnreadOnly(true)} className={cn('min-h-10 rounded-full px-4 text-sm font-black transition', showUnreadOnly ? 'bg-[#f3c56b] text-[#24170d]' : 'bg-white/10 text-white/70 hover:bg-white/15')}>Chưa đọc</button>
            </div>
          </div>

          <div className="max-h-[calc(100vh-20rem)] overflow-y-auto px-3 pb-4 lg:max-h-[calc(100vh-22rem)]">
            {isLoadingConversations ? (
              <div className="space-y-3 p-3">
                {Array.from({ length: 5 }).map((_, index) => <div key={index} className="h-20 animate-pulse rounded-3xl bg-white/10" />)}
              </div>
            ) : conversations.length === 0 ? (
              <div className="m-3 rounded-3xl border border-white/10 bg-white/8 p-6 text-center text-sm font-bold text-white/65">Chưa có đoạn chat nào.</div>
            ) : conversations.map((conversation) => (
              <button
                key={conversation.id}
                type="button"
                onClick={() => setActiveConversation(conversation)}
                className={cn('mb-2 flex w-full items-center gap-3 rounded-3xl p-3 text-left transition', activeConversation?.id === conversation.id ? 'bg-[#fffaf1] text-[#24170d] shadow-xl shadow-black/20' : 'text-white hover:bg-white/10')}
              >
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#f3c56b] text-sm font-black text-[#24170d] shadow-lg">
                  {conversation.room_number || 'P?'}
                </div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center justify-between gap-2">
                    <p className="truncate text-sm font-black">Phòng {conversation.room_number || '—'} · {conversation.tenant_name || 'Khách thuê'}</p>
                    {conversation.admin_unread_count > 0 && <span className="rounded-full bg-[#006dff] px-2 py-0.5 text-[10px] font-black text-white">{conversation.admin_unread_count}</span>}
                  </div>
                  <p className="mt-1 truncate text-xs font-bold opacity-70">{conversation.last_message?.body || conversation.building_name || 'Bắt đầu trò chuyện'}</p>
                </div>
              </button>
            ))}
          </div>
        </aside>

        <main className="flex min-h-[620px] flex-col bg-[radial-gradient(circle_at_20%_0%,rgba(243,197,107,0.24),transparent_32%),linear-gradient(180deg,#fffaf1,#f4efe6)]">
          {activeConversation ? (
            <>
              <header className="flex items-center justify-between gap-4 border-b border-[#3d2a18]/10 bg-white/55 p-5 backdrop-blur-md">
                <div className="flex min-w-0 items-center gap-3">
                  <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-[#24170d] text-sm font-black text-[#f3c56b]">{activeConversation.room_number || 'P?'}</div>
                  <div className="min-w-0">
                    <h2 className="truncate text-xl font-black tracking-[-0.03em] text-[#24170d]">Phòng {activeConversation.room_number || '—'} - {activeConversation.tenant_name || 'Khách thuê'}</h2>
                    <p className="truncate text-xs font-black uppercase tracking-[0.16em] text-[#8b5e34]">{activeConversation.building_name || 'Tòa nhà'} · {activeConversation.tenant_phone || 'Chưa có SĐT'}</p>
                  </div>
                </div>
                <div className="flex items-center gap-2 rounded-full border border-[#0f766e]/15 bg-[#0f766e]/10 px-3 py-2 text-xs font-black text-[#0f5f59]">
                  {echo ? <Wifi className="h-4 w-4" /> : <WifiOff className="h-4 w-4" />}
                  {echo ? 'Realtime' : 'Đang nối'}
                </div>
              </header>

              <div className="flex-1 overflow-y-auto p-5">
                {isLoadingMessages ? (
                  <div className="space-y-3">
                    {Array.from({ length: 6 }).map((_, index) => <div key={index} className={cn('h-14 animate-pulse rounded-3xl bg-[#3d2a18]/10', index % 2 ? 'ml-auto w-2/5' : 'w-3/5')} />)}
                  </div>
                ) : messages.length === 0 ? (
                  <div className="flex h-full items-center justify-center text-center">
                    <div className="rounded-[2rem] border border-[#3d2a18]/10 bg-white/65 p-8 shadow-xl shadow-[#6b3f1d]/8">
                      <Users className="mx-auto h-10 w-10 text-[#8b5e34]" />
                      <p className="mt-3 text-lg font-black text-[#24170d]">Chưa có tin nhắn</p>
                      <p className="mt-1 text-sm font-bold text-[#6f6254]">Gửi lời chào cho khách thuê để bắt đầu hỗ trợ.</p>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-3">
                    {messages.map((message) => {
                      const isMine = message.sender_role === ADMIN_ROLE
                      return (
                        <div key={message.id} className={cn('flex', isMine ? 'justify-end' : 'justify-start')}>
                          <div className={cn('max-w-[78%] rounded-[1.45rem] px-4 py-3 shadow-sm', isMine ? 'bg-[#24170d] text-white' : 'border border-[#3d2a18]/10 bg-white text-[#24170d]')}>
                            <p className="whitespace-pre-wrap text-sm font-bold leading-6">{message.body}</p>
                            <p className={cn('mt-1 text-[10px] font-black uppercase tracking-[0.14em]', isMine ? 'text-white/45' : 'text-[#8b5e34]/70')}>{message.optimistic ? 'Đang gửi...' : formatDateTime(message.created_at)}</p>
                          </div>
                        </div>
                      )
                    })}
                    <div ref={messagesEndRef} />
                  </div>
                )}
              </div>

              {errorMessage && <div className="mx-5 mb-3 rounded-2xl border border-rose-900/10 bg-rose-50 px-4 py-3 text-sm font-bold text-rose-700">{errorMessage}</div>}

              <form className="border-t border-[#3d2a18]/10 bg-white/70 p-4 backdrop-blur-md" onSubmit={(event) => { event.preventDefault(); void handleSendMessage() }}>
                <div className="flex items-end gap-3 rounded-[1.6rem] border border-[#3d2a18]/10 bg-[#fffaf1] p-2 shadow-inner">
                  <textarea
                    value={input}
                    onChange={(event) => setInput(event.target.value)}
                    onKeyDown={(event) => {
                      if (event.key === 'Enter' && !event.shiftKey) {
                        event.preventDefault()
                        void handleSendMessage()
                      }
                    }}
                    placeholder="Nhập tin nhắn cho khách thuê..."
                    className="max-h-32 min-h-12 flex-1 resize-none bg-transparent px-3 py-3 text-sm font-bold text-[#24170d] outline-none placeholder:text-[#8b5e34]/50"
                  />
                  <button type="submit" disabled={!input.trim() || isSending} className="flex min-h-12 min-w-12 items-center justify-center rounded-2xl bg-[#0f766e] text-white shadow-lg shadow-[#0f766e]/20 transition hover:bg-[#0b5f59] disabled:cursor-not-allowed disabled:opacity-45">
                    <Send className="h-5 w-5" />
                  </button>
                </div>
              </form>
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
    </section>
  )
}

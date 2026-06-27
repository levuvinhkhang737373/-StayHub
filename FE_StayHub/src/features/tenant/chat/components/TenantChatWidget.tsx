import { useCallback, useEffect, useRef, useState } from 'react'
import { MessageCircle, Minus, Send, X } from 'lucide-react'
import { AnimatePresence, motion } from 'motion/react'
import { cn } from '../../../../shared/lib/utils/cn'
import { formatDateTime } from '../../../../shared/lib/utils/format'
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
  const messagesEndRef = useRef<HTMLDivElement | null>(null)

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
    if (!body || isSending) return

    const optimisticMessage: ChatMessageResource = {
      id: Date.now() * -1,
      conversation_id: conversation?.id || 0,
      sender_type: 'tenant',
      sender_id: conversation?.tenant_id || 0,
      sender_role: TENANT_ROLE,
      sender_role_label: 'Khách thuê',
      body,
      created_at: new Date().toISOString(),
      optimistic: true,
    }

    setInput('')
    setMessages((current) => [...current, optimisticMessage])
    setIsSending(true)
    setErrorMessage(null)
    try {
      const response = await sendTenantChatMessage(body)
      if (response.result) {
        setConversation(response.result.conversation)
        setMessages((current) => [...current.filter((item) => item.id !== optimisticMessage.id), response.result.message])
      }
    } catch (error: any) {
      setMessages((current) => current.filter((item) => item.id !== optimisticMessage.id))
      setInput(body)
      setErrorMessage(error?.message || 'Không thể gửi tin nhắn.')
    } finally {
      setIsSending(false)
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
                {messages.map((message) => {
                  const isMine = message.sender_role === TENANT_ROLE
                  return (
                    <div key={message.id} className={cn('flex', isMine ? 'justify-end' : 'justify-start')}>
                      <div className={cn('max-w-[82%] rounded-[1.35rem] px-4 py-3 shadow-sm', isMine ? 'bg-[#0f766e] text-white' : 'border border-[#3d2a18]/10 bg-white text-[#24170d]')}>
                        <p className="whitespace-pre-wrap text-sm font-bold leading-6">{message.body}</p>
                        <p className={cn('mt-1 text-[10px] font-black uppercase tracking-[0.13em]', isMine ? 'text-white/55' : 'text-[#8b5e34]/70')}>{message.optimistic ? 'Đang gửi...' : formatDateTime(message.created_at)}</p>
                      </div>
                    </div>
                  )
                })}
                <div ref={messagesEndRef} />
              </div>
            )}
          </div>

          {errorMessage && <div className="border-t border-rose-900/10 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700">{errorMessage}</div>}

          <form className="border-t border-[#3d2a18]/10 bg-white p-3" onSubmit={(event) => { event.preventDefault(); void handleSendMessage() }}>
            <div className="flex items-end gap-2 rounded-3xl border border-[#3d2a18]/10 bg-[#f9f5ec] p-2">
              <textarea
                value={input}
                onChange={(event) => setInput(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault()
                    void handleSendMessage()
                  }
                }}
                placeholder="Nhập tin nhắn..."
                className="max-h-24 min-h-11 flex-1 resize-none bg-transparent px-3 py-2 text-sm font-bold text-[#24170d] outline-none placeholder:text-[#8b5e34]/50"
              />
              <button type="submit" disabled={!input.trim() || isSending} className="flex min-h-11 min-w-11 items-center justify-center rounded-full bg-[#0f766e] text-white shadow-lg shadow-[#0f766e]/20 transition hover:bg-[#0b5f59] disabled:cursor-not-allowed disabled:opacity-45" aria-label="Gửi tin nhắn">
                <Send className="h-4 w-4" />
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

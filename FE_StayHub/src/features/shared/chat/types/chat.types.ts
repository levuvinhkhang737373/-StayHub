export interface ChatMessageResource {
  id: number
  conversation_id: number
  sender_type: 'admin' | 'tenant' | string
  sender_id: number
  sender_role: 1 | 2 | number
  sender_role_label?: string | null
  sender_name?: string | null
  sender_avatar_url?: string | null
  body: string
  attachments?: string[]
  queued_at?: string | null
  sent_at?: string | null
  read_at?: string | null
  created_at?: string | null
  updated_at?: string | null
  optimistic?: boolean
}

export interface ChatConversationResource {
  id: number
  building_id: number
  building_name?: string | null
  room_id: number
  room_number?: string | null
  tenant_id: number
  tenant_name?: string | null
  tenant_phone?: string | null
  tenant_avatar_url?: string | null
  manager_admin_id: number
  manager_name?: string | null
  last_message_id?: number | null
  last_message?: ChatMessageResource | null
  last_message_at?: string | null
  tenant_unread_count: number
  admin_unread_count: number
  tenant_last_read_at?: string | null
  admin_last_read_at?: string | null
  status: number
  status_label?: string | null
  created_at?: string | null
  updated_at?: string | null
}

export interface ChatConversationListResult {
  data: ChatConversationResource[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export interface ChatMessageListResult {
  conversation?: ChatConversationResource
  data: ChatMessageResource[]
  pagination: {
    has_more: boolean
    oldest_id?: number | null
    newest_id?: number | null
  }
}

export interface ChatSendResult {
  message: ChatMessageResource
  conversation: ChatConversationResource
}

export interface ChatMessageSentEvent {
  message: ChatMessageResource
  conversation: ChatConversationResource
}

export interface ChatConversationReadEvent {
  reader_type: 'admin' | 'tenant' | string
  conversation: ChatConversationResource
}

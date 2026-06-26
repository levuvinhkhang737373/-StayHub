import { apiRequest } from '../../../../shared/lib/api/api-client'
import type {
  ChatConversationListResult,
  ChatConversationResource,
  ChatMessageListResult,
  ChatSendResult,
} from '../types/chat.types'

export interface AdminChatConversationFilters {
  keyword?: string
  building_id?: number
  unread?: boolean
  page?: number
  per_page?: number
}

export interface ChatMessageFilters {
  before_id?: number
  per_page?: number
}

export async function fetchAdminChatConversations(params: AdminChatConversationFilters = {}) {
  return apiRequest<ChatConversationListResult>({
    url: 'admin/chat/conversations',
    method: 'GET',
    params,
  })
}

export async function fetchAdminChatMessages(conversationId: number, params: ChatMessageFilters = {}) {
  return apiRequest<ChatMessageListResult>({
    url: `admin/chat/conversations/${conversationId}/messages`,
    method: 'GET',
    params,
  })
}

export async function sendAdminChatMessage(conversationId: number, body: string) {
  return apiRequest<ChatSendResult>({
    url: `admin/chat/conversations/${conversationId}/messages`,
    method: 'POST',
    data: { body },
  })
}

export async function markAdminChatRead(conversationId: number) {
  return apiRequest<ChatConversationResource>({
    url: `admin/chat/conversations/${conversationId}/read`,
    method: 'PATCH',
  })
}

export async function fetchTenantChatConversation() {
  return apiRequest<ChatConversationResource>({
    url: 'tenant/chat/conversation',
    method: 'GET',
  })
}

export async function fetchTenantChatMessages(params: ChatMessageFilters = {}) {
  return apiRequest<ChatMessageListResult>({
    url: 'tenant/chat/messages',
    method: 'GET',
    params,
  })
}

export async function sendTenantChatMessage(body: string) {
  return apiRequest<ChatSendResult>({
    url: 'tenant/chat/messages',
    method: 'POST',
    data: { body },
  })
}

export async function markTenantChatRead() {
  return apiRequest<ChatConversationResource>({
    url: 'tenant/chat/read',
    method: 'PATCH',
  })
}

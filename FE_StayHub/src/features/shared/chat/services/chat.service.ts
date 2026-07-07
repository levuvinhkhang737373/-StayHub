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
  unread?: boolean | number
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

export async function sendAdminChatMessage(conversationId: number, body: string, images?: File[]) {
  const formData = new FormData()
  if (body) formData.append('body', body)
  if (images && images.length > 0) {
    images.forEach((img) => formData.append('images[]', img))
  }

  return apiRequest<ChatSendResult>({
    url: `admin/chat/conversations/${conversationId}/messages`,
    method: 'POST',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export async function markAdminChatRead(conversationId: number) {
  return apiRequest<ChatConversationResource>({
    url: `admin/chat/conversations/${conversationId}/read`,
    method: 'PATCH',
  })
}

export async function fetchAdminDirectConversations(params: AdminChatConversationFilters = {}) {
  return apiRequest<ChatConversationListResult>({
    url: 'admin/chat/direct-conversations',
    method: 'GET',
    params,
  })
}

export async function fetchAdminDirectMessages(conversationId: number, params: ChatMessageFilters = {}) {
  return apiRequest<ChatMessageListResult>({
    url: `admin/chat/direct-conversations/${conversationId}/messages`,
    method: 'GET',
    params,
  })
}

export async function sendAdminDirectMessage(conversationId: number, body: string, images?: File[]) {
  const formData = new FormData()
  if (body) formData.append('body', body)
  if (images && images.length > 0) {
    images.forEach((img) => formData.append('images[]', img))
  }

  return apiRequest<ChatSendResult>({
    url: `admin/chat/direct-conversations/${conversationId}/messages`,
    method: 'POST',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export async function markAdminDirectRead(conversationId: number) {
  return apiRequest<ChatConversationResource>({
    url: `admin/chat/direct-conversations/${conversationId}/read`,
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

export async function sendTenantChatMessage(body: string, images?: File[]) {
  const formData = new FormData()
  if (body) formData.append('body', body)
  if (images && images.length > 0) {
    images.forEach((img) => formData.append('images[]', img))
  }

  return apiRequest<ChatSendResult>({
    url: 'tenant/chat/messages',
    method: 'POST',
    data: formData,
    headers: { 'Content-Type': 'multipart/form-data' },
  })
}

export async function markTenantChatRead() {
  return apiRequest<ChatConversationResource>({
    url: 'tenant/chat/read',
    method: 'PATCH',
  })
}

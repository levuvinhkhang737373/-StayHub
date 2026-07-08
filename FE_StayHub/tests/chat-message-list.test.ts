import test from 'node:test'
import assert from 'node:assert/strict'
import type { ChatMessageResource } from '../src/features/shared/chat/types/chat.types.ts'
import { appendRealtimeChatMessage, appendUniqueChatMessage, confirmOptimisticChatMessage } from '../src/features/shared/chat/utils/chat-message-list.ts'

function makeMessage(id: number, overrides: Partial<ChatMessageResource> = {}): ChatMessageResource {
  return {
    id,
    conversation_id: 1,
    sender_type: 'admin',
    sender_id: 1,
    sender_role: 2,
    body: '',
    attachments: [],
    ...overrides,
  }
}

test('does not append a message that already exists after API response and realtime event race', () => {
  const savedImageMessage = makeMessage(101, {
    attachments: ['https://cdn.example.test/chats/water-meter.jpg'],
    created_at: '2026-07-08 23:02:00',
  })

  const afterApiResponse = appendUniqueChatMessage([], savedImageMessage)
  const afterRealtimeEvent = appendUniqueChatMessage(afterApiResponse, savedImageMessage)

  assert.equal(afterRealtimeEvent.length, 1)
  assert.deepEqual(afterRealtimeEvent[0]?.attachments, ['https://cdn.example.test/chats/water-meter.jpg'])
})

test('keeps one saved image message when realtime event arrives before API confirmation', () => {
  const optimisticMessage = makeMessage(-1, {
    sender_type: 'admin',
    sender_id: 7,
    sender_role: 2,
    body: '',
    attachments: ['blob:http://localhost/preview-water-meter'],
    optimistic: true,
  })
  const savedImageMessage = makeMessage(101, {
    sender_type: 'admin',
    sender_id: 7,
    sender_role: 2,
    body: '',
    attachments: ['https://cdn.example.test/chats/water-meter.jpg'],
  })

  const afterRealtimeEvent = appendRealtimeChatMessage([optimisticMessage], savedImageMessage)
  const afterApiConfirmation = confirmOptimisticChatMessage(afterRealtimeEvent, optimisticMessage.id, savedImageMessage)

  assert.equal(afterApiConfirmation.length, 1)
  assert.equal(afterApiConfirmation[0]?.id, savedImageMessage.id)
  assert.equal(afterApiConfirmation[0]?.optimistic, undefined)
  assert.deepEqual(afterApiConfirmation[0]?.attachments, ['https://cdn.example.test/chats/water-meter.jpg'])
})

test('keeps unrelated optimistic messages when another sender message arrives', () => {
  const optimisticMessage = makeMessage(-1, {
    sender_type: 'admin',
    sender_id: 7,
    sender_role: 2,
    body: 'Đang gửi',
    optimistic: true,
  })
  const tenantMessage = makeMessage(202, {
    sender_type: 'tenant',
    sender_id: 99,
    sender_role: 1,
    body: 'Tin nhắn mới',
  })

  const messages = appendRealtimeChatMessage([optimisticMessage], tenantMessage)

  assert.equal(messages.length, 2)
  assert.equal(messages[0]?.id, optimisticMessage.id)
  assert.equal(messages[1]?.id, tenantMessage.id)
})

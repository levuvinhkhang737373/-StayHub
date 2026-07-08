import type { ChatMessageResource } from '../types/chat.types'

function isSameSender(left: ChatMessageResource, right: ChatMessageResource): boolean {
  return left.sender_type === right.sender_type
    && Number(left.sender_id) === Number(right.sender_id)
    && Number(left.sender_role) === Number(right.sender_role)
}

export function appendUniqueChatMessage(messages: ChatMessageResource[], message: ChatMessageResource): ChatMessageResource[] {
  return messages.some((item) => item.id === message.id) ? messages : [...messages, message]
}

export function appendRealtimeChatMessage(messages: ChatMessageResource[], message: ChatMessageResource): ChatMessageResource[] {
  if (messages.some((item) => item.id === message.id)) {
    return messages
  }

  const pendingMineIndex = messages.findIndex((item) => item.optimistic && isSameSender(item, message))

  if (pendingMineIndex === -1) {
    return [...messages, message]
  }

  return messages.map((item, index) => index === pendingMineIndex ? message : item)
}

export function confirmOptimisticChatMessage(messages: ChatMessageResource[], optimisticId: number, savedMessage: ChatMessageResource): ChatMessageResource[] {
  if (messages.some((item) => item.id === savedMessage.id)) {
    return messages.filter((item) => item.id !== optimisticId)
  }

  let replaced = false
  const nextMessages = messages.map((item) => {
    if (item.id !== optimisticId) {
      return item
    }

    replaced = true
    return savedMessage
  })

  return replaced ? nextMessages : [...nextMessages, savedMessage]
}

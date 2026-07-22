import { useEffect } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { fetchMessages, postMessage } from '@/services/chat.service';
import { useSocket } from '@/hooks/useSocket';
import type { ChatMessageItem, MessagesResponse } from '@/types/chat.types';
import type { MessageReceivePayload } from '@/types/socket-events';

export const messagesQueryKey = (roomId: string) => ['messages', roomId] as const;

function mapWsMessage(msg: MessageReceivePayload): ChatMessageItem {
  return {
    id: msg.messageId,
    senderType: msg.senderType,
    senderId: msg.senderId,
    content: msg.content,
    attachmentUrl: msg.attachmentUrl ?? null,
    createdAt: msg.createdAt,
    tempId: msg.tempId,
    clientStatus: 'sent',
    readStatus: { delivered: true, read: false },
  };
}

function mergeIncomingMessage(
  old: MessagesResponse | undefined,
  msg: MessageReceivePayload,
): MessagesResponse {
  const messages = old?.messages ?? [];
  if (msg.tempId) {
    const idx = messages.findIndex((m) => m.tempId === msg.tempId);
    if (idx >= 0) {
      const next = [...messages];
      next[idx] = mapWsMessage(msg);
      return { messages: next, hasMore: old?.hasMore ?? false };
    }
  }
  if (messages.some((m) => m.id === msg.messageId)) {
    return { messages, hasMore: old?.hasMore ?? false };
  }
  return {
    messages: [...messages, mapWsMessage(msg)],
    hasMore: old?.hasMore ?? false,
  };
}

export function useMessages(roomId: string | null) {
  const queryClient = useQueryClient();
  const { on, emit } = useSocket();

  const query = useQuery({
    queryKey: roomId ? messagesQueryKey(roomId) : ['messages', 'none'],
    queryFn: () => fetchMessages(roomId!),
    enabled: !!roomId,
    staleTime: 10_000,
  });

  useEffect(() => {
    if (!roomId) return undefined;
    return on('message:receive', (msg) => {
      if (msg.roomId !== roomId) return;
      queryClient.setQueryData<MessagesResponse>(messagesQueryKey(roomId), (old) =>
        mergeIncomingMessage(old, msg),
      );
    });
  }, [roomId, on, queryClient]);

  const sendMutation = useMutation({
    mutationFn: async ({ content }: { content: string; tempId: string }) => {
      if (!roomId) throw new Error('roomId required');
      return postMessage(roomId, content);
    },
    onMutate: async ({ content, tempId }) => {
      if (!roomId) return {};
      await queryClient.cancelQueries({ queryKey: messagesQueryKey(roomId) });
      const previous = queryClient.getQueryData<MessagesResponse>(messagesQueryKey(roomId));
      const optimistic: ChatMessageItem = {
        id: tempId,
        tempId,
        senderType: 'agent',
        senderId: 'me',
        content,
        createdAt: new Date().toISOString(),
        clientStatus: 'pending',
        readStatus: { delivered: false, read: false },
      };
      queryClient.setQueryData<MessagesResponse>(messagesQueryKey(roomId), (old) => ({
        messages: [...(old?.messages ?? []), optimistic],
        hasMore: old?.hasMore ?? false,
      }));
      emit('typing:stop', { roomId });
      return { previous };
    },
    onError: (_err, { tempId }, ctx) => {
      if (!roomId) return;
      queryClient.setQueryData<MessagesResponse>(messagesQueryKey(roomId), (old) => {
        if (!old) return old;
        return {
          ...old,
          messages: old.messages.map((m) =>
            m.tempId === tempId ? { ...m, clientStatus: 'failed' as const } : m,
          ),
        };
      });
      if (ctx?.previous) {
        queryClient.setQueryData(messagesQueryKey(roomId), ctx.previous);
      }
    },
    onSuccess: (data, { tempId }) => {
      if (!roomId) return;
      queryClient.setQueryData<MessagesResponse>(messagesQueryKey(roomId), (old) => {
        if (!old) return old;
        return {
          ...old,
          messages: old.messages.map((m) =>
            m.tempId === tempId
              ? {
                  ...m,
                  id: data.messageId,
                  createdAt: data.createdAt,
                  clientStatus: 'sent' as const,
                  readStatus: { delivered: true, read: false },
                }
              : m,
          ),
        };
      });
    },
  });

  return {
    messages: query.data?.messages ?? [],
    isLoading: query.isLoading,
    error: query.error,
    sendMessage: (content: string) => {
      const tempId = crypto.randomUUID();
      sendMutation.mutate({ content, tempId });
      if (roomId) {
        emit('message:send', { roomId, content, tempId });
      }
    },
    isSending: sendMutation.isPending,
  };
}

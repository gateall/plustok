import { useEffect } from 'react';
import { useSocket } from './useSocket';
import { useAdminRealtimeInvalidation } from './useAdminStats';

/**
 * Admin dashboard — Phase 2 WS 이벤트 재사용 (room:update, ai:update, read:update).
 */
export function useAdminSocket(): void {
  const { socket, isConnected } = useSocket();
  const invalidate = useAdminRealtimeInvalidation();

  useEffect(() => {
    if (!socket || !isConnected) return;

    const onRoomUpdate = () => invalidate();
    const onAiUpdate = () => invalidate();
    const onReadUpdate = () => invalidate();

    socket.on('room:update', onRoomUpdate);
    socket.on('ai:update', onAiUpdate);
    socket.on('read:update', onReadUpdate);

    return () => {
      socket.off('room:update', onRoomUpdate);
      socket.off('ai:update', onAiUpdate);
      socket.off('read:update', onReadUpdate);
    };
  }, [socket, isConnected, invalidate]);
}

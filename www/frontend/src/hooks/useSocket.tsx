import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import { io, type Socket } from 'socket.io-client';
import { getAccessToken } from '@/services/api.client';
import type {
  ClientToServerEvents,
  ClientEventName,
  ServerToClientEvents,
  ServerEventName,
} from '@/types/socket-events';

const WS_URL = import.meta.env.VITE_WS_URL ?? 'http://localhost:3001';

export interface UseSocketReturn {
  socket: Socket<ServerToClientEvents, ClientToServerEvents> | null;
  isConnected: boolean;
  error: string | null;
  connect: (token: string) => void;
  disconnect: () => void;
  emit: <E extends ClientEventName>(
    event: E,
    ...args: Parameters<ClientToServerEvents[E]>
  ) => void;
  on: <E extends ServerEventName>(
    event: E,
    callback: ServerToClientEvents[E],
  ) => () => void;
  off: <E extends ServerEventName>(
    event: E,
    callback?: ServerToClientEvents[E],
  ) => void;
}

const SocketContext = createContext<UseSocketReturn | null>(null);

export function SocketProvider({ children }: { children: ReactNode }) {
  const value = useSocketInternal();
  return <SocketContext.Provider value={value}>{children}</SocketContext.Provider>;
}

export function useSocket(): UseSocketReturn {
  const ctx = useContext(SocketContext);
  if (!ctx) {
    throw new Error('useSocket must be used within SocketProvider');
  }
  return ctx;
}

function useSocketInternal(): UseSocketReturn {
  const socketRef = useRef<Socket<ServerToClientEvents, ClientToServerEvents> | null>(null);
  const [isConnected, setIsConnected] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const disconnect = useCallback(() => {
    if (socketRef.current) {
      socketRef.current.removeAllListeners();
      socketRef.current.disconnect();
      socketRef.current = null;
    }
    setIsConnected(false);
  }, []);

  const connect = useCallback(
    (token: string) => {
      disconnect();
      const socket = io(WS_URL, {
        auth: { token },
        reconnection: true,
        reconnectionDelay: 1000,
        reconnectionDelayMax: 5000,
        transports: ['websocket', 'polling'],
      });

      socket.on('connect', () => {
        setIsConnected(true);
        setError(null);
      });

      socket.on('disconnect', () => {
        setIsConnected(false);
      });

      socket.on('connect_error', (err) => {
        setError(err.message);
        setIsConnected(false);
      });

      socket.on('error', (payload) => {
        setError(payload.message);
      });

      socketRef.current = socket;
    },
    [disconnect],
  );

  const emit = useCallback<UseSocketReturn['emit']>((event, ...args) => {
    if (socketRef.current?.connected) {
      socketRef.current.emit(event, ...(args as Parameters<ClientToServerEvents[typeof event]>));
    }
  }, []);

  const on = useCallback<UseSocketReturn['on']>((event, callback) => {
    if (!socketRef.current) {
      return () => undefined;
    }
    socketRef.current.on(event, callback as never);
    return () => {
      socketRef.current?.off(event, callback as never);
    };
  }, []);

  const off = useCallback<UseSocketReturn['off']>((event, callback) => {
    if (callback) {
      socketRef.current?.off(event, callback as never);
    } else {
      socketRef.current?.off(event);
    }
  }, []);

  useEffect(() => {
    const token = getAccessToken();
    if (token) {
      connect(token);
    }
    return () => disconnect();
  }, [connect, disconnect]);

  return useMemo(
    () => ({
      socket: socketRef.current,
      isConnected,
      error,
      connect,
      disconnect,
      emit,
      on,
      off,
    }),
    [isConnected, error, connect, disconnect, emit, on, off],
  );
}

import { describe, expect, it, vi, beforeEach } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { createElement, type ReactNode } from 'react';
import { SocketProvider, useSocket } from '@/hooks/useSocket';

const mockSocket = {
  on: vi.fn(),
  off: vi.fn(),
  emit: vi.fn(),
  disconnect: vi.fn(),
  removeAllListeners: vi.fn(),
  connected: false,
};

vi.mock('socket.io-client', () => ({
  io: vi.fn(() => mockSocket),
}));

vi.mock('@/services/api.client', () => ({
  getAccessToken: vi.fn(() => 'test-jwt-token'),
  setAccessToken: vi.fn(),
}));

describe('useSocket', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockSocket.connected = false;
    mockSocket.on.mockImplementation((event: string, cb: (...args: unknown[]) => void) => {
      if (event === 'connect') {
        mockSocket.connected = true;
        cb();
      }
    });
  });

  function wrapper({ children }: { children: ReactNode }) {
    return createElement(SocketProvider, null, children);
  }

  it('connects with JWT auth.token (no Bearer)', async () => {
    const { io } = await import('socket.io-client');
    renderHook(() => useSocket(), { wrapper });
    await waitFor(() => {
      expect(io).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          auth: { token: 'test-jwt-token' },
        }),
      );
    });
  });

  it('emit forwards events when connected', async () => {
    mockSocket.connected = true;
    const { result } = renderHook(() => useSocket(), { wrapper });
    await waitFor(() => expect(result.current.isConnected).toBe(true));

    act(() => {
      result.current.emit('room:join', { roomId: 'room-1' });
    });

    expect(mockSocket.emit).toHaveBeenCalledWith('room:join', { roomId: 'room-1' });
  });

  it('on registers listener and returns unsubscribe', async () => {
    const { result } = renderHook(() => useSocket(), { wrapper });
    const cb = vi.fn();
    let unsub: () => void = () => undefined;

    act(() => {
      unsub = result.current.on('message:receive', cb);
    });

    expect(mockSocket.on).toHaveBeenCalledWith('message:receive', cb);
    act(() => unsub());
    expect(mockSocket.off).toHaveBeenCalledWith('message:receive', cb);
  });
});

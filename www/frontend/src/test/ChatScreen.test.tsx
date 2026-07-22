import { describe, expect, it, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { MemoryRouter } from 'react-router-dom';
import { ChatScreen } from '@/components/Chat/ChatScreen';

const emit = vi.fn();
const on = vi.fn(() => () => undefined);

vi.mock('@/features/auth/AuthProvider', () => ({
  useAuth: () => ({
    user: { id: 'agent-1', name: '테스트', loginId: 'admin', role: 'admin', status: 'online' },
    loading: false,
    login: vi.fn(),
    logout: vi.fn(),
  }),
}));

vi.mock('@/hooks/useSocket', () => ({
  SocketProvider: ({ children }: { children: React.ReactNode }) => children,
  useSocket: () => ({
    socket: null,
    isConnected: true,
    error: null,
    connect: vi.fn(),
    disconnect: vi.fn(),
    emit,
    on,
    off: vi.fn(),
  }),
}));

vi.mock('@/hooks/useChatRooms', () => ({
  useChatRooms: () => ({
    rooms: [
      {
        id: 'room-1',
        customer: { id: 'c1', name: '홍길동', phoneMasked: '010-****-5678', tags: [] },
        inquiryType: '설치',
        status: 'active',
        unreadCount: 1,
        contractProbability: 80,
        updatedAt: new Date().toISOString(),
      },
    ],
    isLoading: false,
    error: null,
    refetch: vi.fn(),
  }),
}));

const sendMessage = vi.fn();
vi.mock('@/hooks/useMessages', () => ({
  useMessages: () => ({
    messages: [],
    isLoading: false,
    error: null,
    sendMessage,
    isSending: false,
  }),
}));

vi.mock('@/hooks/useAiRecommendations', () => ({
  useAiRecommendations: () => ({
    recommendation: null,
    isLoading: false,
    isProcessing: false,
    refetch: vi.fn(),
  }),
}));

function renderChat() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ChatScreen />
      </MemoryRouter>
    </QueryClientProvider>,
  );
}

describe('ChatScreen', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders 3-panel layout', async () => {
    const user = userEvent.setup();
    renderChat();
    expect(screen.getByText('채팅')).toBeInTheDocument();
    expect(screen.getByText('AI 추천')).toBeInTheDocument();
    await user.click(screen.getByText('홍길동'));
    expect(screen.getByPlaceholderText(/메시지/i)).toBeInTheDocument();
  });

  it('selects room and sends message', async () => {
    const user = userEvent.setup();
    renderChat();

    await user.click(screen.getByText('홍길동'));
    const input = screen.getByPlaceholderText(/메시지/i);
    await user.type(input, 'Hello');
    await user.click(screen.getByRole('button', { name: '전송' }));

    expect(sendMessage).toHaveBeenCalledWith('Hello');
  });

  it('emits typing:start on input', async () => {
    const user = userEvent.setup();
    renderChat();

    await user.click(screen.getByText('홍길동'));
    await user.type(screen.getByPlaceholderText(/메시지/i), 'H');

    expect(emit).toHaveBeenCalledWith('typing:start', { roomId: 'room-1' });
  });
});

import clsx from 'clsx';
import type { ChatRoomItem } from '@/types/chat.types';

interface ChatRoomListProps {
  rooms: ChatRoomItem[];
  selectedRoomId: string | null;
  isLoading: boolean;
  onSelectRoom: (room: ChatRoomItem) => void;
}

export function ChatRoomList({
  rooms,
  selectedRoomId,
  isLoading,
  onSelectRoom,
}: ChatRoomListProps) {
  return (
    <div className="flex h-full flex-col">
      <div className="border-b border-acep-border p-4">
        <h2 className="text-lg font-bold text-slate-900">채팅</h2>
        <p className="text-xs text-slate-500">{rooms.length}개 상담방</p>
      </div>
      <div className="flex-1 overflow-y-auto">
        {isLoading && (
          <p className="p-4 text-sm text-slate-500">목록 불러오는 중…</p>
        )}
        {!isLoading && rooms.length === 0 && (
          <p className="p-4 text-sm text-slate-500">상담방이 없습니다.</p>
        )}
        {rooms.map((room) => (
          <button
            key={room.id}
            type="button"
            onClick={() => onSelectRoom(room)}
            className={clsx(
              'w-full border-b border-acep-border p-3 text-left transition hover:bg-slate-50',
              selectedRoomId === room.id && 'bg-blue-50',
            )}
          >
            <div className="flex items-start justify-between gap-2">
              <div className="min-w-0 flex-1">
                <div className="truncate text-sm font-medium text-slate-900">
                  {room.customer.name}
                </div>
                <div className="truncate text-xs text-slate-500">{room.inquiryType}</div>
              </div>
              {room.unreadCount > 0 && (
                <span className="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-xs text-white">
                  {room.unreadCount}
                </span>
              )}
            </div>
            <div className="mt-1 flex items-center gap-2 text-xs text-slate-400">
              <StatusBadge status={room.status} />
              {room.contractProbability > 0 && (
                <span>계약 {room.contractProbability}%</span>
              )}
            </div>
          </button>
        ))}
      </div>
    </div>
  );
}

function StatusBadge({ status }: { status: string }) {
  const label =
    status === 'new' ? '신규' : status === 'active' ? '진행' : status === 'closed' ? '종료' : status;
  return (
    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-slate-600">
      {label}
    </span>
  );
}

import type { ConsultDateGroup } from '@/utils/groupConsultsByDate';
import type { ConsultListItem } from '@/types/consult.types';
import ConsultPcTableRow from './ConsultPcTableRow';

type ConsultPcTableProps = {
  groups: ConsultDateGroup[];
  selectedKeys: Set<string>;
  activeId?: string;
  onToggle: (key: string, checked: boolean) => void;
  onRowClick: (id: string) => void;
  allKeys: string[];
  onToggleAll: (checked: boolean) => void;
  allSelected: boolean;
};

export default function ConsultPcTable({
  groups,
  selectedKeys,
  activeId,
  onToggle,
  onRowClick,
  allKeys,
  onToggleAll,
  allSelected,
}: ConsultPcTableProps) {
  return (
    <div className="consult-pc-table min-w-0">
      {groups.map((group) => (
        <section key={group.key} className="consult-date-group mb-6 min-w-0" aria-label={group.label}>
          <div className="consult-date-header mb-2 flex flex-wrap items-center justify-between gap-2 rounded-lg bg-slate-100 px-3 py-2 min-[1024px]:px-4">
            <div className="flex flex-wrap items-baseline gap-2">
              <strong className="text-sm font-bold text-slate-900">{group.primary}</strong>
              {group.secondary && (group.primary === '오늘' || group.primary === '어제') ? (
                <span className="text-sm text-slate-600">{group.secondary}</span>
              ) : null}
            </div>
            <div className="flex flex-wrap items-center gap-2 text-xs text-slate-600">
              <span className="rounded-full bg-white px-2 py-0.5 font-medium">총 {group.totalCount}건</span>
              {group.newCount > 0 ? (
                <span className="rounded-full bg-indigo-100 px-2 py-0.5 font-medium text-indigo-800">
                  신규 {group.newCount}건
                </span>
              ) : null}
            </div>
          </div>

          <div className="consult-table-scroll min-w-0 overflow-x-auto">
            <table className="consult-table w-full min-w-0 table-fixed border-collapse text-left">
              <thead className="consult-table-header">
                <tr className="border-b border-slate-200 bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                  <th scope="col" className="w-11 px-2 py-2.5">
                    <input
                      type="checkbox"
                      className="consult-checkbox h-4 w-4 rounded border-slate-300 text-indigo-600"
                      checked={allSelected && allKeys.length > 0}
                      onChange={(e) => onToggleAll(e.target.checked)}
                      aria-label="현재 페이지 전체 선택"
                    />
                  </th>
                  <th scope="col" className="w-[5.5rem] px-2 py-2.5">
                    상태
                  </th>
                  <th scope="col" className="w-[5.5rem] px-2 py-2.5">
                    접수
                  </th>
                  <th scope="col" className="w-[9.5rem] px-2 py-2.5">
                    상담번호
                  </th>
                  <th scope="col" className="w-[10rem] px-2 py-2.5">
                    고객
                  </th>
                  <th scope="col" className="hidden w-[7.5rem] px-2 py-2.5 min-[1280px]:table-cell">
                    사이트
                  </th>
                  <th scope="col" className="px-2 py-2.5">
                    문의 내용
                  </th>
                  <th scope="col" className="hidden w-[6.5rem] px-2 py-2.5 min-[1024px]:table-cell">
                    담당
                  </th>
                  <th scope="col" className="hidden w-[5.5rem] px-2 py-2.5 min-[1280px]:table-cell">
                    최근응답
                  </th>
                  <th scope="col" className="w-[4.5rem] px-2 py-2.5">
                    관리
                  </th>
                </tr>
              </thead>
              <tbody>
                {group.items.map((consult: ConsultListItem) => {
                  const key = `${consult.source}-${consult.id}`;
                  return (
                    <ConsultPcTableRow
                      key={key}
                      consult={consult}
                      selected={selectedKeys.has(key)}
                      active={activeId === consult.id}
                      onSelect={(checked) => onToggle(key, checked)}
                      onClick={() => onRowClick(consult.id)}
                    />
                  );
                })}
              </tbody>
            </table>
          </div>
        </section>
      ))}
    </div>
  );
}

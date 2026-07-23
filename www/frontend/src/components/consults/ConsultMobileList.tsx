import type { ConsultListItem } from '@/types/consult.types';
import ConsultCard from './ConsultCard';

type ConsultMobileListProps = {
  consults: ConsultListItem[];
};

export default function ConsultMobileList({ consults }: ConsultMobileListProps) {
  return (
    <ul className="min-w-0 space-y-3 md:hidden" aria-label="상담 목록">
      {consults.map((consult) => (
        <li key={`${consult.source}-${consult.id}`} className="min-w-0">
          <ConsultCard consult={consult} />
        </li>
      ))}
    </ul>
  );
}

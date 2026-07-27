import { CONSULT_STATUS_LABELS } from '@/types/consult.types';
import type { BadgeTone } from './Badge';

const STATUS_TONES: Record<string, BadgeTone> = {
  new: 'info',
  progress: 'warning',
  consulting: 'success',
  contracted: 'info',
  installed: 'neutral',
  hold: 'warning',
  canceled: 'danger',
  cancelled: 'danger',
  open: 'warning',
  active: 'success',
  closed: 'neutral',
  pending: 'warning',
  in_progress: 'success',
  completed: 'neutral',
  quoted: 'info',
};

export function consultStatusBadgeProps(status: string): { label: string; tone: BadgeTone } {
  const normalized = status.toLowerCase();
  const label = CONSULT_STATUS_LABELS[normalized] ?? CONSULT_STATUS_LABELS[status] ?? status;
  const tone = STATUS_TONES[normalized] ?? STATUS_TONES[status] ?? 'neutral';
  return { label, tone };
}

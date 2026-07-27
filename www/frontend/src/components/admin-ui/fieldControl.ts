/** Shared A11y + touch-target classes for form controls (Sprint 3.2 RC2). */

export const focusRingClass =
  'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[var(--pt-color-primary)]/40 focus-visible:ring-offset-2';

export const touchTargetClass = 'min-h-11 min-w-11';

export const controlDisabledClass = 'cursor-not-allowed opacity-50';

/** Links hint/error copy to a control via aria-describedby. */
export function fieldDescribedBy(fieldId: string, error?: string, hint?: string): string | undefined {
  const ids: string[] = [];
  if (error) ids.push(`${fieldId}-error`);
  else if (hint) ids.push(`${fieldId}-hint`);
  return ids.length > 0 ? ids.join(' ') : undefined;
}

export function fieldErrorId(fieldId: string): string {
  return `${fieldId}-error`;
}

export function fieldHintId(fieldId: string): string {
  return `${fieldId}-hint`;
}

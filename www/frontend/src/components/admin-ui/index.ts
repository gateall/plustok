/** PM FROZEN components (9) — names locked after M1; no renames. */

export { default as AdminCard } from './AdminCard';

export { default as AdminCardHeader } from './AdminCardHeader';

export { default as AdminCardBody } from './AdminCardBody';

export { default as InfoRow } from './InfoRow';

export { default as StatusBadge, consultStatusBadgeProps } from './StatusBadge';

export { default as PageHeader } from './PageHeader';

export { default as PageTitle } from './PageTitle';

export { default as QuickAction } from './QuickAction';

export { default as BottomNavigation } from './BottomNavigation';



/** Legacy aliases — same implementation as FROZEN names above. */

export { default as ResponsiveCard } from './ResponsiveCard';

export { default as CardHeader } from './AdminCardHeader';

export { default as CardBody } from './AdminCardBody';



/** Domain helpers — built on FROZEN components; not PM frozen names. */

export { default as SearchBox } from './SearchBox';

export { default as EmptyState } from './EmptyState';

export { default as AdminErrorState } from './AdminErrorState';

export { default as ActionButton } from './ActionButton';

export { default as AdminListCards } from './AdminListCards';

export { default as AdminPageShell } from './AdminPageShell';

export { default as AdminPcListPage } from './AdminPcListPage';

export type { AdminPcListColumn, AdminPcListRow } from './AdminPcListPage';

export { default as Badge } from './Badge';

export { default as BottomAction } from './BottomAction';

export { default as Button } from './Button';

export { default as ChartCard } from './ChartCard';

export { default as DashboardCard } from './DashboardCard';

export { default as Dialog } from './Dialog';

export { default as Drawer } from './Drawer';

export { default as AdminCardFooter } from './AdminCardFooter';

export { default as FloatingAction } from './FloatingAction';

export { default as MobileMenu } from './MobileMenu';

export { default as Pagination } from './Pagination';

export { default as ResponsiveTable } from './ResponsiveTable';

export { default as Sidebar } from './Sidebar';

export { default as StatusCard } from './StatusCard';



export { default as Loading } from './Loading';

export { default as ConfirmDialog } from './ConfirmDialog';

export { default as Timeline } from './Timeline';

export { default as Upload } from './Upload';

export type { UploadProgress } from './Upload';

export { default as AttachmentList } from './AttachmentList';

export type { AttachmentItem } from './AttachmentList';

export { default as Tag, presetTags, createTag, tagChipClass, DEFAULT_CONSULT_TAGS } from './Tag';

export type { TagData } from './Tag';

export { default as TagBadge } from './TagBadge';

export { default as TagFilter } from './TagFilter';

export { default as Input } from './Input';
export type { InputProps } from './Input';

export { default as Textarea } from './Textarea';
export type { TextareaProps } from './Textarea';

export { default as Select } from './Select';
export type { SelectProps, SelectOption } from './Select';

export { default as Card } from './Card';

export { default as Table } from './Table';

export { default as Avatar } from './Avatar';
export type { AvatarProps } from './Avatar';

export { default as Tabs } from './Tabs';
export type { TabItem } from './Tabs';

export { default as Accordion } from './Accordion';
export type { AccordionItem } from './Accordion';

export { default as Modal } from './Modal';

export { default as Alert } from './Alert';
export type { AlertTone } from './Alert';

export { default as Skeleton } from './Skeleton';

export { default as Checkbox } from './Checkbox';
export type { CheckboxProps } from './Checkbox';

export { default as Radio } from './Radio';
export type { RadioProps } from './Radio';

export { default as Switch } from './Switch';
export type { SwitchProps } from './Switch';

export {
  resolveFileType,
  getFileTypeMeta,
  isImageFile,
  formatFileSize,
} from './fileIcons';

export type { FileTypeKey } from './fileIcons';

export type { BadgeProps, BadgeTone } from './Badge';

/** Sprint 3.2 RC2 — common composites & aliases (re-exported for single import surface). */

export { default as SectionTitle } from '@/components/common/SectionTitle';

export { default as InfoCard } from '@/components/common/InfoCard';

export { default as StatCard } from '@/components/common/StatCard';

export { default as SearchBar } from '@/components/common/SearchBar';

export { default as FilterBar } from '@/components/common/FilterBar';

export { default as EmptyView } from '@/components/common/EmptyView';

export { default as LoadingView } from '@/components/common/LoadingView';


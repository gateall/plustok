import type { LucideIcon } from 'lucide-react';
import {
  File,
  FileArchive,
  FileImage,
  FileSpreadsheet,
  FileText,
  Presentation,
} from 'lucide-react';

export type FileTypeKey =
  | 'pdf'
  | 'docx'
  | 'xlsx'
  | 'pptx'
  | 'zip'
  | 'png'
  | 'jpg'
  | 'webp'
  | 'unknown';

type FileTypeMeta = {
  key: FileTypeKey;
  label: string;
  icon: LucideIcon;
  bgClass: string;
  textClass: string;
};

const FILE_TYPE_META: Record<FileTypeKey, FileTypeMeta> = {
  pdf: {
    key: 'pdf',
    label: 'PDF',
    icon: FileText,
    bgClass: 'bg-red-50',
    textClass: 'text-red-600',
  },
  docx: {
    key: 'docx',
    label: 'DOCX',
    icon: FileText,
    bgClass: 'bg-blue-50',
    textClass: 'text-blue-600',
  },
  xlsx: {
    key: 'xlsx',
    label: 'XLSX',
    icon: FileSpreadsheet,
    bgClass: 'bg-emerald-50',
    textClass: 'text-emerald-600',
  },
  pptx: {
    key: 'pptx',
    label: 'PPTX',
    icon: Presentation,
    bgClass: 'bg-orange-50',
    textClass: 'text-orange-600',
  },
  zip: {
    key: 'zip',
    label: 'ZIP',
    icon: FileArchive,
    bgClass: 'bg-amber-50',
    textClass: 'text-amber-700',
  },
  png: {
    key: 'png',
    label: 'PNG',
    icon: FileImage,
    bgClass: 'bg-indigo-50',
    textClass: 'text-indigo-600',
  },
  jpg: {
    key: 'jpg',
    label: 'JPG',
    icon: FileImage,
    bgClass: 'bg-indigo-50',
    textClass: 'text-indigo-600',
  },
  webp: {
    key: 'webp',
    label: 'WEBP',
    icon: FileImage,
    bgClass: 'bg-indigo-50',
    textClass: 'text-indigo-600',
  },
  unknown: {
    key: 'unknown',
    label: 'FILE',
    icon: File,
    bgClass: 'bg-slate-100',
    textClass: 'text-slate-500',
  },
};

const EXT_MAP: Record<string, FileTypeKey> = {
  pdf: 'pdf',
  doc: 'docx',
  docx: 'docx',
  xls: 'xlsx',
  xlsx: 'xlsx',
  ppt: 'pptx',
  pptx: 'pptx',
  zip: 'zip',
  rar: 'zip',
  '7z': 'zip',
  png: 'png',
  jpg: 'jpg',
  jpeg: 'jpg',
  webp: 'webp',
  gif: 'png',
};

export function resolveFileType(name: string, mimeType?: string): FileTypeKey {
  const ext = name.split('.').pop()?.toLowerCase() ?? '';
  if (ext && EXT_MAP[ext]) return EXT_MAP[ext];

  if (mimeType) {
    if (mimeType.includes('pdf')) return 'pdf';
    if (mimeType.includes('word') || mimeType.includes('document')) return 'docx';
    if (mimeType.includes('sheet') || mimeType.includes('excel')) return 'xlsx';
    if (mimeType.includes('presentation') || mimeType.includes('powerpoint')) return 'pptx';
    if (mimeType.includes('zip') || mimeType.includes('compressed')) return 'zip';
    if (mimeType.includes('png')) return 'png';
    if (mimeType.includes('jpeg') || mimeType.includes('jpg')) return 'jpg';
    if (mimeType.includes('webp')) return 'webp';
    if (mimeType.startsWith('image/')) return 'png';
  }

  return 'unknown';
}

export function getFileTypeMeta(name: string, mimeType?: string): FileTypeMeta {
  const key = resolveFileType(name, mimeType);
  return FILE_TYPE_META[key];
}

export function isImageFile(name: string, mimeType?: string): boolean {
  const key = resolveFileType(name, mimeType);
  return key === 'png' || key === 'jpg' || key === 'webp';
}

export function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes}B`;
  if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)}KB`;
  return `${(bytes / (1024 * 1024)).toFixed(1)}MB`;
}

export { FILE_TYPE_META };

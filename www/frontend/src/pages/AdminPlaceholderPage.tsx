type AdminPlaceholderPageProps = {
  title: string;
  description?: string;
  phase?: string;
};

/** Placeholder for admin routes not yet implemented (Phase 3+). */
export default function AdminPlaceholderPage({
  title,
  description,
  phase = 'Phase 3',
}: AdminPlaceholderPageProps) {
  const defaultDescription = `${phase}에서 제공될 예정입니다. 홈 탭에서 대시보드를 확인하세요.`;

  return (
    <div className="min-w-0 py-4 md:py-6">
      <div className="flex min-h-[40vh] flex-col items-center justify-center px-4 py-12 text-center">
        <p className="page-title">{title}</p>
        <p className="page-description mt-2 max-w-sm">{description ?? defaultDescription}</p>
      </div>
    </div>
  );
}

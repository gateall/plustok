import PageHeader from '../components/common/PageHeader';
import EmptyState from '../components/common/EmptyState';

export default function AdminProductsPage() {
  return (
    <div className="min-w-0 py-4 md:py-6">
      <PageHeader
        title="상품 관리"
        description="서비스 상품 정보를 관리합니다."
      />
      <div className="mt-6 p-12 bg-white rounded-xl border border-slate-200 shadow-sm flex items-center justify-center min-h-[400px]">
        <div className="text-center">
          <EmptyState 
            title="PARTIAL — API BLOCK" 
            description="현재 상품 관리 화면을 위한 REST API(GET /admin/products 등)가 백엔드에 구현되어 있지 않습니다. 기능 개발이 완료될 때까지 기존 PHP 관리자 화면을 이용해 주세요."
          />
          <div className="mt-8">
            <a 
              href="/admin/products/" 
              className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              기존 PHP 상품 관리로 이동
            </a>
          </div>
        </div>
      </div>
    </div>
  );
}

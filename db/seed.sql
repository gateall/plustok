-- PlusTok 통합 CRM — V1.0 시드
-- 적용: mysql -u <user> -p <dbname> < seed.sql  (schema.sql 이후)
-- ⚠️ 아래 api_key는 초기 발급값이다. 운영 전/노출 시 관리자에서 재발급할 것.
-- ⚠️ 관리자 계정은 여기서 만들지 않는다. 서버에서 /admin/setup.php로 최초 super 계정을 생성한다.

SET NAMES utf8mb4;

-- 사이트(도메인) ---------------------------------------------------------
INSERT INTO sites (site_code, site_name, domain, brand, division, persona, api_key) VALUES
('smarttoktok','스마트톡톡','smarttoktok.com','SmartTokTok','통신사업','LG유플러스 대표번호 가입센터입니다.','8369d27c9826b1e1295b79d6e3992400b5e3f81c2b2c22fad641c1511b9f3094'),
('lg15441644','LG15441644','lg15441644.kr','LG15441644','통신가입','기업 인터넷·070·대표번호 가입센터입니다.','8a9925a7a9c29922e4cbb3e79b774812cf3c20b667056e68f2e2ab32c69765c6'),
('hompyshop','홈피샵','hompyshop.com','HompyShop','웹제작','AI 홈페이지 제작 상담입니다.','0e69d946a10a107a01cd18f33e6c4b737c0a5af2dbb6d6d7e1d8d316c113d5e8'),
('showform','쇼폼','showform.kr','ShowForm','AI 플랫폼','쇼폼 AI 랜딩 제작입니다.','f2b97801874692411f6e7bdbce0b859d793c8049428489dd8ec6b2e017f7ad11'),
('callmap','콜맵','callmap.kr','CallMap','광고플랫폼','지도·플레이스 상위노출 상담입니다.','bf0768d0b3cb536a87e8d5978c31141dfd85c14108ea71222363d60640343b0c'),
('hongpansa','홍판사','hongpansa.kr','HongPansa','판촉사업','판촉·홍보 상담입니다.','abae574511b2e1c24a526bde094d8197c0dae86a84249656c66d49ad4c1252fd'),
('oncap24','온캡24','oncap24.com','Oncap24','중개서비스','이사·공사 견적을 도와드립니다.','e440572c99d21f375d5b4e4ba6272c995c355bbe014f87b4bf65e2d9d4daf8e1'),
('nuguupso','누구업소','nuguupso.com','nuguupso','플랫폼 사업','원하는 공사·제품을 등록하면 업체가 견적을 제안합니다.','4a9b747e79f7ce428e0b3c2e4ec7e391e665f1307b8ddf8f2437a355610e9e9c');

-- 상품(브랜드별 기본 카테고리) ------------------------------------------
INSERT INTO products (brand, category, product_name, sort_order) VALUES
('SmartTokTok','통신','대표번호',1),
('SmartTokTok','통신','070전화',2),
('SmartTokTok','통신','기업인터넷',3),
('LG15441644','통신','기업인터넷',1),
('LG15441644','통신','070전화',2),
('LG15441644','통신','대표번호',3),
('LG15441644','통신','IPTV',4),
('LG15441644','통신','CCTV',5),
('LG15441644','통신','결합상품',6),
('HompyShop','웹','기업홈페이지',1),
('HompyShop','웹','쇼핑몰',2),
('HompyShop','웹','랜딩페이지',3),
('HompyShop','웹','SEO',4),
('HompyShop','웹','유지관리',5),
('ShowForm','웹','AI 랜딩페이지',1),
('ShowForm','웹','설문/폼',2),
('CallMap','광고','플레이스 등록',1),
('CallMap','광고','지도 상위',2),
('CallMap','광고','지역광고',3),
('HongPansa','판촉','판촉물',1),
('HongPansa','판촉','블로그체험단',2),
('HongPansa','판촉','상위노출',3),
('HongPansa','판촉','홍보',4),
('Oncap24','중개','가정이사',1),
('Oncap24','중개','사무실이사',2),
('Oncap24','중개','보관이사',3),
('Oncap24','중개','용달',4),
('Oncap24','중개','공사',5),
('nuguupso','중개','인테리어',1),
('nuguupso','중개','청소',2),
('nuguupso','중개','설비',3),
('nuguupso','중개','광고',4);

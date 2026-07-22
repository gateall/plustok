#!/bin/bash
set -e
echo "=== Node.js 포터블 설치 시작 ==="
mkdir -p ~/local
cd ~/local

echo "=== Node.js 다운로드 중 ==="
curl -O https://nodejs.org/dist/v20.18.1/node-v20.18.1-linux-x64.tar.xz

echo "=== 압축 해제 중 ==="
tar -xf node-v20.18.1-linux-x64.tar.xz

echo "=== 설치 확인 ==="
~/local/node-v20.18.1-linux-x64/bin/node -v
~/local/node-v20.18.1-linux-x64/bin/npm -v

echo "=== 완료! ==="

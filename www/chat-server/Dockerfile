FROM node:20-alpine

WORKDIR /app

COPY package.json package-lock.json ./

RUN npm ci

COPY tsconfig.json ./
COPY src ./src

RUN npm run build && npm prune --omit=dev

ENV NODE_ENV=production

# Render sets PORT at runtime; server.ts reads process.env.PORT
EXPOSE 3000

CMD ["npm", "start"]

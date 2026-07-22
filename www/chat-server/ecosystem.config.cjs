/**
 * PM2 ecosystem — chat-server (PATH B VPS / hybrid)
 * Usage: npm run build && pm2 start ecosystem.config.cjs
 */
module.exports = {
  apps: [
    {
      name: 'acep-chat-server',
      script: 'dist/server.js',
      cwd: __dirname,
      instances: 1,
      exec_mode: 'fork',
      autorestart: true,
      max_memory_restart: '512M',
      env_production: {
        NODE_ENV: 'production',
        CHAT_SERVER_PORT: '3001',
      },
    },
  ],
};

import type { IncomingMessage, ServerResponse } from 'node:http';
import type { Server } from 'socket.io';

async function readBody(req: IncomingMessage): Promise<string> {
  const chunks: Buffer[] = [];
  for await (const chunk of req) {
    chunks.push(chunk as Buffer);
  }
  return Buffer.concat(chunks).toString('utf8');
}

export async function handleInternalBroadcast(
  req: IncomingMessage,
  res: ServerResponse,
  io: Server,
): Promise<void> {
  const expected = process.env.CHAT_INTERNAL_SECRET?.trim()
    ?? process.env.JWT_SECRET?.trim()
    ?? '';
  const provided = (req.headers['x-chat-internal-secret'] as string | undefined)?.trim() ?? '';

  if (expected === '' || provided === '' || provided !== expected) {
    res.writeHead(403, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, error: 'Forbidden' }));
    return;
  }

  let body: { event?: string; payload?: Record<string, unknown>; roomId?: string };
  try {
    body = JSON.parse(await readBody(req)) as {
      event?: string;
      payload?: Record<string, unknown>;
      roomId?: string;
    };
  } catch {
    res.writeHead(400, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, error: 'Invalid JSON' }));
    return;
  }

  const event = body.event?.trim();
  if (!event || !body.payload || typeof body.payload !== 'object') {
    res.writeHead(400, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ success: false, error: 'event and payload required' }));
    return;
  }

  const roomId = body.roomId?.trim();
  if (roomId) {
    io.to(`room:${roomId}`).emit(event, body.payload);
  } else {
    io.emit(event, body.payload);
  }
  res.writeHead(200, { 'Content-Type': 'application/json' });
  res.end(JSON.stringify({ success: true, event }));
}

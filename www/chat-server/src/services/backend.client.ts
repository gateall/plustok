const base = () => (process.env.BACKEND_URL ?? 'http://localhost/api/v1').replace(/\/$/, '');

export async function assertRoomAccess(
  roomId: string,
  token: string,
): Promise<boolean> {
  const res = await fetch(`${base()}/chats/${roomId}`, {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
  });
  return res.ok;
}

export async function sendMessageViaRest(
  roomId: string,
  token: string,
  body: { content: string; source?: string },
): Promise<{ messageId: string; createdAt: string } | null> {
  const res = await fetch(`${base()}/chats/${roomId}/messages`, {
    method: 'POST',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify(body),
  });
  if (!res.ok) {
    return null;
  }
  const json = (await res.json()) as {
    success: boolean;
    data?: { messageId: string; createdAt: string };
  };
  return json.success && json.data ? json.data : null;
}

export async function markReadViaRest(
  roomId: string,
  token: string,
  messageIds: string[],
  readerType: 'agent' | 'customer',
): Promise<number> {
  const res = await fetch(`${base()}/chats/${roomId}/read`, {
    method: 'PUT',
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({ messageIds, readerType }),
  });
  if (!res.ok) {
    return 0;
  }
  const json = (await res.json()) as { data?: { updatedCount?: number } };
  return json.data?.updatedCount ?? 0;
}

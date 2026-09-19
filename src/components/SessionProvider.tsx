"use client";

import { createContext, useCallback, useContext, useEffect, useState } from "react";
import type { SessionState } from "@/lib/types";

type SessionContextValue = {
  session: SessionState | null;
  loading: boolean;
  refresh: () => Promise<SessionState | null>;
};

const SessionContext = createContext<SessionContextValue>({
  session: null,
  loading: true,
  refresh: async () => null,
});

export function SessionProvider({ children }: { children: React.ReactNode }) {
  const [session, setSession] = useState<SessionState | null>(null);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    const res = await fetch("/api/session", { cache: "no-store" });
    const data = (await res.json()) as SessionState;
    setSession(data);
    setLoading(false);
    return data;
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return (
    <SessionContext.Provider value={{ session, loading, refresh }}>
      {children}
    </SessionContext.Provider>
  );
}

export function useSession() {
  return useContext(SessionContext);
}

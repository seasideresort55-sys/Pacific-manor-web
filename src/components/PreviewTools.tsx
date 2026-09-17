"use client";

import { useState } from "react";
import { useSession } from "./SessionProvider";

export function PreviewTools() {
  const { refresh } = useSession();
  const [open, setOpen] = useState(false);
  const [busy, setBusy] = useState(false);

  async function apply(body: Record<string, unknown>) {
    setBusy(true);
    await fetch("/api/session", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });
    await refresh();
    setBusy(false);
  }

  return (
    <div className="fixed bottom-4 right-4 z-50 max-w-[16rem] text-sm">
      <button
        type="button"
        className="min-h-11 rounded-full bg-deep px-4 text-cream shadow-card"
        onClick={() => setOpen((value) => !value)}
      >
        預覽工具
      </button>
      {open ? (
        <div className="mt-2 grid gap-2 rounded-2xl border border-sand bg-white p-3 shadow-card">
          <p className="text-deep">僅供本站預覽切換狀態，不是正式後台。</p>
          <button className="btn-secondary !min-h-11 !text-base" disabled={busy} onClick={() => apply({ reset: true })}>
            重設為訪客
          </button>
          <button
            className="btn-secondary !min-h-11 !text-base"
            disabled={busy}
            onClick={() => apply({ quizOutcome: "pass", isMember: false, authVerified: false })}
          >
            模擬已通過
          </button>
          <button
            className="btn-secondary !min-h-11 !text-base"
            disabled={busy}
            onClick={() => apply({ quizOutcome: "review", isMember: false })}
          >
            模擬待人工
          </button>
          <button
            className="btn-secondary !min-h-11 !text-base"
            disabled={busy}
            onClick={() => apply({ quizOutcome: "reject", isMember: false })}
          >
            模擬未通過
          </button>
          <button
            className="btn-secondary !min-h-11 !text-base"
            disabled={busy}
            onClick={() =>
              apply({
                quizOutcome: "pass",
                isMember: true,
                memberPlan: "seascape_list",
                authVerified: true,
                authProvider: "sms",
              })
            }
          >
            模擬月租會員
          </button>
        </div>
      ) : null}
    </div>
  );
}

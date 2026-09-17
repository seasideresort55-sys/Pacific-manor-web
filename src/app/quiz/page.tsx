import { QuizForm } from "@/components/QuizForm";

export default function QuizPage() {
  return (
    <div className="wave-band">
      <div className="mx-auto max-w-3xl px-4 py-12">
        <p className="text-sm tracking-[0.2em] text-ocean">了解是否適合</p>
        <p className="mt-2 text-xl leading-8 text-[#3d5a66]">
          一題一題來，字夠大、選項都有說明。結果只有三種：通過、待人工、未通過。
        </p>
        <div className="mt-8">
          <QuizForm />
        </div>
      </div>
    </div>
  );
}

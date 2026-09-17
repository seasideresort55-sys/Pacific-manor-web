import type { Config } from "tailwindcss";

const config: Config = {
  content: ["./src/**/*.{ts,tsx}"],
  theme: {
    extend: {
      colors: {
        ocean: "#2E5E73",
        deep: "#1C3D4C",
        cream: "#F6F1E7",
        sand: "#E8DFD0",
        foam: "#FFFcf7",
        coral: "#C47B5A",
        moss: "#5A7A62",
        mist: "#8AA4B0",
      },
      fontFamily: {
        sans: [
          "PingFang TC",
          "Microsoft JhengHei",
          "Noto Sans CJK TC",
          "WenQuanYi Micro Hei",
          "Noto Sans TC",
          "sans-serif",
        ],
      },
      boxShadow: {
        card: "0 12px 40px rgba(28, 61, 76, 0.08)",
      },
      borderRadius: {
        xl2: "1.25rem",
      },
    },
  },
  plugins: [],
};

export default config;

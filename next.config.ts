import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  reactStrictMode: true,
  async redirects() {
    return [
      {
        source: "/",
        has: [{ type: "query", key: "page_id", value: "11240" }],
        destination: "/membership/verify",
        permanent: true,
      },
      {
        source: "/",
        has: [{ type: "query", key: "page_id", value: "2107" }],
        destination: "/membership",
        permanent: true,
      },
      {
        source: "/",
        has: [{ type: "query", key: "page_id", value: "901" }],
        destination: "/member",
        permanent: true,
      },
    ];
  },
};

export default nextConfig;

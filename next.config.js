// next.config.js
/** @type {import('next').NextConfig} */
const nextConfig = {
    reactStrictMode: false,
    async rewrites() {
      return [
        {
          source: '/uploads/:filename*',
          destination: '/api/uploads/:filename*',
        },
      ]}
  };
  
  module.exports = nextConfig;
  
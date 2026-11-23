FROM node:22-bookworm-slim AS production

LABEL org.opencontainers.image.title="NEXVARY CX WebRTC Bridge"
LABEL org.opencontainers.image.vendor="NEXVARY"

RUN useradd --system --uid 1002 --no-create-home --shell /sbin/nologin rtcbridge

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci --omit=dev --quiet

COPY src/ ./src/

# Simple Node.js healthcheck that hits the /health endpoint
RUN printf 'import http from "node:http";\n\
const port = process.env.RTC_BRIDGE_PORT || "3000";\n\
http.get(`http://127.0.0.1:${port}/health`, r => {\n\
  process.exit(r.statusCode === 200 ? 0 : 1);\n\
}).on("error", () => process.exit(1));\n' > /app/healthcheck.mjs

USER rtcbridge

# RTC_BIND_ADDRESS=0.0.0.0 must be set in compose.yaml; defaults to 127.0.0.1
# for non-containerised deployments.
EXPOSE 3000

HEALTHCHECK --interval=20s --timeout=5s --start-period=10s --retries=3 \
    CMD node /app/healthcheck.mjs

ENTRYPOINT ["node", "src/index.js"]

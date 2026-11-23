# ── Stage 1: Build FreeSWITCH 1.10.11 from source ─────────────────────────────
FROM debian:bookworm-slim AS builder

ARG FS_VERSION=1.10.11

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential autoconf automake libtool libtool-bin pkg-config \
    git curl ca-certificates \
    libssl-dev libcurl4-openssl-dev libpcre3-dev libsqlite3-dev \
    libpq-dev libpq5 \
    libopus-dev libvpx-dev libspeex-dev libspeexdsp-dev \
    libedit-dev libncurses5-dev \
    libldns-dev \
    liblua5.4-dev \
    yasm nasm \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /usr/src
RUN git clone --depth=1 --branch v${FS_VERSION} \
    https://github.com/signalwire/freeswitch.git freeswitch

WORKDIR /usr/src/freeswitch

# Enable the modules we need; disable heavy/unused ones to reduce build time
COPY modules.conf /usr/src/freeswitch/modules.conf

RUN ./bootstrap.sh -j \
    && ./configure \
        --prefix=/usr/local \
        --with-openssl \
        --with-pgsql \
        --without-sctp \
        --enable-core-libedit-support \
        --disable-static \
    && make -j$(nproc) \
    && make install \
    && make sounds-install moh-install

# ── Stage 2: Minimal runtime image ────────────────────────────────────────────
FROM debian:bookworm-slim AS runtime

LABEL org.opencontainers.image.title="NEXVARY CX FreeSWITCH"
LABEL org.opencontainers.image.version="1.10.11"
LABEL org.opencontainers.image.vendor="NEXVARY"

RUN apt-get update && apt-get install -y --no-install-recommends \
    libssl3 libcurl4 libpcre3 libsqlite3-0 \
    libpq5 \
    libopus0 libvpx7 libspeex1 libspeexdsp1 \
    libedit2 libncurses6 \
    libldns3 \
    liblua5.4-0 \
    curl netcat-openbsd ca-certificates gettext-base \
    && rm -rf /var/lib/apt/lists/*

# FreeSWITCH binaries and libraries from builder
COPY --from=builder /usr/local/bin/freeswitch    /usr/local/bin/freeswitch
COPY --from=builder /usr/local/bin/fs_cli        /usr/local/bin/fs_cli
COPY --from=builder /usr/local/lib/freeswitch    /usr/local/lib/freeswitch
COPY --from=builder /usr/local/share/freeswitch  /usr/local/share/freeswitch

# Default configuration shipped with the image; entrypoint copies to volume on first run
COPY --from=builder /usr/local/etc/freeswitch    /usr/share/nexvary/freeswitch-defaults

# Our configuration overrides (applied on top of the defaults)
COPY conf/ /usr/share/nexvary/freeswitch-overrides/

# Runtime directories
RUN mkdir -p \
        /etc/freeswitch \
        /var/log/freeswitch \
        /var/run/freeswitch \
        /var/lib/freeswitch/recordings \
        /var/lib/freeswitch/storage \
        /var/lib/freeswitch/db \
    && ldconfig

VOLUME ["/etc/freeswitch", "/var/log/freeswitch", "/var/lib/freeswitch/recordings"]

# SIP UDP/TCP, SIP TLS, WS (mod_sofia), WSS (mod_sofia), ESL, RTP range
EXPOSE 5060/udp 5060/tcp 5061/tcp 5066/tcp 7443/tcp 8021/tcp
EXPOSE 16384-16484/udp

COPY --chmod=755 docker-entrypoint.sh /docker-entrypoint.sh

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD fs_cli -p "${FREESWITCH_ESL_PASSWORD:-}" -x 'status' 2>/dev/null | grep -q 'UP' || exit 1

ENTRYPOINT ["/docker-entrypoint.sh"]

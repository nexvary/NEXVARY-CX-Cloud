# ── Stage 1: Build spandsp3 ────────────────────────────────────────────────────
# spandsp3 is not in Debian bookworm (only 0.0.6 is available); FreeSWITCH
# 1.10.11 requires >= 3.0. Build from the FreeSWITCH project fork.
FROM debian:bookworm-slim AS spandsp-builder

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential autoconf automake libtool libtool-bin pkg-config git ca-certificates \
    libssl-dev libtiff-dev \
    && rm -rf /var/lib/apt/lists/*

RUN git clone --depth=1 https://github.com/freeswitch/spandsp.git /usr/src/spandsp \
    && cd /usr/src/spandsp \
    && ./bootstrap.sh \
    && ./configure --prefix=/usr/local \
    && make -j$(nproc) \
    && make install

# ── Stage 2: Build FreeSWITCH 1.10.11 from source ─────────────────────────────
FROM debian:bookworm-slim AS builder

ARG FS_VERSION=1.10.11

RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential autoconf automake libtool libtool-bin pkg-config \
    git curl ca-certificates \
    # TLS and SSL
    libssl-dev \
    # Curl (mod_curl, core HTTP)
    libcurl4-openssl-dev \
    # Regex
    libpcre3-dev \
    # SQLite (FreeSWITCH internal DB)
    libsqlite3-dev \
    # PostgreSQL (mod_pgsql, mod_cdr_pg)
    libpq-dev libpq5 \
    # Audio codecs
    libopus-dev libvpx-dev libspeex-dev libspeexdsp-dev \
    # Command-line editing
    libedit-dev libncurses5-dev \
    # DNS (mod_lcr)
    libldns-dev \
    # Lua scripting
    liblua5.4-dev \
    # sofia-sip is built from source below (Debian bookworm has 1.12.11 — FS requires >= 1.13.17)
    # Required for sofia-sip source build: glib, gettext/autopoint
    libglib2.0-dev gettext \
    # libtiff needed by spandsp3 at link time
    libtiff-dev \
    # nasm/yasm for codec assembly optimisations
    yasm nasm \
    && rm -rf /var/lib/apt/lists/*

# Install spandsp3 from the spandsp-builder stage.
# IMPORTANT: the glob `spandsp*` only copies the top-level spandsp.h — it does NOT
# recurse into the spandsp/ subdirectory that contains telephony.h, t38_core.h, etc.
# Use two explicit COPY directives: one for the header file, one for the directory.
COPY --from=spandsp-builder /usr/local/include/spandsp.h    /usr/local/include/
COPY --from=spandsp-builder /usr/local/include/spandsp/     /usr/local/include/spandsp/
COPY --from=spandsp-builder /usr/local/lib/libspandsp*       /usr/local/lib/
COPY --from=spandsp-builder /usr/local/lib/pkgconfig/spandsp.pc /usr/local/lib/pkgconfig/
RUN ldconfig

# Build sofia-sip >= 1.13.17 from the FreeSWITCH fork.
# Debian bookworm ships 1.12.11 which FS 1.10.11's configure rejects.
RUN git clone --depth=1 https://github.com/freeswitch/sofia-sip.git /usr/src/sofia-sip \
    && cd /usr/src/sofia-sip \
    && ([ -f autogen.sh ] && sh autogen.sh || autoreconf -fi) \
    && ./configure --prefix=/usr/local --disable-static \
    && make -j$(nproc) \
    && make install \
    && ldconfig \
    && rm -rf /usr/src/sofia-sip

WORKDIR /usr/src
RUN git clone --depth=1 --branch v${FS_VERSION} \
    https://github.com/signalwire/freeswitch.git freeswitch

WORKDIR /usr/src/freeswitch

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

# ── Stage 3: Minimal runtime image ────────────────────────────────────────────
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
    # sofia-sip-ua0 NOT from apt — built from source (1.12.11 from apt is too old)
    libglib2.0-0 \
    libtiff6 \
    curl netcat-openbsd ca-certificates gettext-base \
    && rm -rf /var/lib/apt/lists/*

# FreeSWITCH binaries and libraries from builder
COPY --from=builder /usr/local/bin/freeswitch    /usr/local/bin/freeswitch
COPY --from=builder /usr/local/bin/fs_cli        /usr/local/bin/fs_cli
COPY --from=builder /usr/local/lib/freeswitch    /usr/local/lib/freeswitch
COPY --from=builder /usr/local/share/freeswitch  /usr/local/share/freeswitch

# spandsp3 runtime library (built from source, not in Debian)
COPY --from=spandsp-builder /usr/local/lib/libspandsp* /usr/local/lib/

# sofia-sip runtime library (built from source, Debian version is too old)
COPY --from=builder /usr/local/lib/libsofia* /usr/local/lib/

# Default configuration shipped with the image; entrypoint copies to volume on first run
COPY --from=builder /usr/local/etc/freeswitch    /usr/share/nexvary/freeswitch-defaults

# Our configuration overrides (applied on top of the defaults)
COPY conf/ /usr/share/nexvary/freeswitch-overrides/

# Shared group for config dir access: app container (uid 1001 nexvary) writes,
# FreeSWITCH (uid 0) owns. GID 2000 must match docker/app/Dockerfile.
RUN groupadd -g 2000 nexvary-pbx

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

# ESL is on 127.0.0.1 (host loopback) with host networking — verify via pid file
HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=5 \
    CMD fs_cli -H 127.0.0.1 -P 8021 -p "${FREESWITCH_ESL_PASSWORD:-}" -x 'status' 2>/dev/null | grep -q 'UP' || exit 1

ENTRYPOINT ["/docker-entrypoint.sh"]

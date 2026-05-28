FROM cloudflare/cloudflared:latest AS cloudflared

FROM alpine:3.22

RUN apk add --no-cache ca-certificates curl
COPY --from=cloudflared /usr/local/bin/cloudflared /usr/local/bin/cloudflared
COPY docker/cloudflared-entrypoint.sh /usr/local/bin/cloudflared-entrypoint.sh
RUN chmod +x /usr/local/bin/cloudflared-entrypoint.sh

ENTRYPOINT ["/usr/local/bin/cloudflared-entrypoint.sh"]
CMD ["--no-autoupdate", "tunnel", "run"]

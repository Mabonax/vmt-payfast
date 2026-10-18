# Fail-closed callback ingress

Direct deployments retain `DnsProviderSource`: the socket peer must match PayFast's documented IPv4 DNS addresses. Forwarded/Referer headers are ignored.

An optional `IngressProviderSource` is now available without changing the `ProviderSource::accepts(string $ip)` interface. It requires:

1. The actual socket peer matches an explicitly configured exact IP (no wildcard or automatic “trust all proxies”).
2. The approved ingress **overwrites**, rather than appends/preserves, `X-PayFast-Client-IP` using an authenticated original client IP.
3. That header contains one valid IP, with no port or proxy chain.
4. The supplied original IP passes the same PayFast DNS source check. DNS failure remains rejection.

```dotenv
PAYFAST_INGRESS_ENABLED=true
PAYFAST_INGRESS_TRUSTED_PEERS=127.0.0.1
```

Enable only after reviewing the real network path. Exact peer IP membership is not sufficient on its own: block direct upstream access and ensure untrusted clients cannot impersonate that peer or supply a preserved header. For a directly internet-facing approved Nginx TLS ingress forwarding to the demo's loopback listener, the relevant upstream stanza is:

```nginx
location / {
    proxy_pass http://127.0.0.1:8086;
    proxy_set_header Host $host;
    proxy_set_header X-PayFast-Client-IP $remote_addr;
    proxy_set_header X-Forwarded-Proto https;
}
```

This fragment assumes TLS is configured in the enclosing approved server, and `$remote_addr` is the true provider socket peer. It is not a full deployment configuration. If another proxy/tunnel precedes Nginx, do not use a cloud relay address as the provider IP. Review that service's authenticated metadata, trusted-proxy/real-IP rules, header overwrite and origin network restrictions. Never copy client-supplied X-Forwarded-For into the trusted header. Configure Laravel's HTTPS/session handling for that approved ingress separately; the package does not change global trusted proxies.

No tunnel or public endpoint is currently approved/configured by this implementation. Unit tests prove trusted/untrusted/missing/malformed header behavior, not a live ingress or provider callback. The default remains disabled. There is no source-verification bypass switch.

Framework/ingress references: https://nginx.org/en/docs/http/ngx_http_proxy_module.html and the official PayFast source validation evidence in docs/PROTOCOL.md.

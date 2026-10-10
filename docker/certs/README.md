# Local HTTPS certificate

The certificate files are intentionally excluded from Git. Generate them once on each development machine with [mkcert](https://github.com/FiloSottile/mkcert):

```sh
mkcert -install
mkcert -cert-file docker/certs/sport-catalog.local.pem \
  -key-file docker/certs/sport-catalog.local-key.pem \
  sport-catalog.local admin.sport-catalog.local
```

Then start the stack with `docker compose up -d`. The site is available at `https://sport-catalog.local` and HTTP redirects to HTTPS.

For hot reloading, run `./vendor/bin/sail npm run dev`; Vite is served at `https://sport-catalog.local:5173` and uses the same certificate.

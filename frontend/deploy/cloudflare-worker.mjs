// Cloudflare Pages advanced mode: only the existing API and CSRF routes are proxied.
// Same-origin cookies preserve the current Sanctum session flow on free subdomains.
export default {
  async fetch(request, env) {
    const incoming = new URL(request.url)
    if (!incoming.pathname.startsWith('/api/') && !incoming.pathname.startsWith('/sanctum/')) {
      return env.ASSETS.fetch(request)
    }

    let origin
    try {
      origin = new URL(env.API_ORIGIN)
      if (origin.protocol !== 'https:' || origin.origin === incoming.origin || origin.username || origin.password || origin.pathname !== '/' || origin.search || origin.hash) throw new Error('Invalid origin')
    } catch {
      return Response.json({ message: 'Layanan API belum dikonfigurasi.' }, { status: 503, headers: { 'Cache-Control': 'no-store' } })
    }

    const target = new URL(incoming.pathname + incoming.search, origin)
    const upstream = new Request(target, request)
    upstream.headers.delete('host')
    // Do not forward browser-supplied proxy identity headers to the backend.
    upstream.headers.delete('forwarded')
    upstream.headers.delete('x-forwarded-host')
    upstream.headers.set('x-forwarded-proto', 'https')
    upstream.headers.delete('x-forwarded-for')
    const ip = request.headers.get('cf-connecting-ip')
    if (ip) upstream.headers.set('x-forwarded-for', ip)

    try {
      const result = await fetch(upstream, { redirect: 'manual' })
      const response = new Response(result.body, result)
      response.headers.set('Cache-Control', 'no-store')
      return response
    } catch {
      return Response.json({ message: 'Layanan API belum dapat dihubungi. Silakan coba lagi.' }, { status: 502, headers: { 'Cache-Control': 'no-store' } })
    }
  },
}

# OAuth 2.1 yetkilendirme sunucusu — MCP hazırlığı (2026.4.10)

**Durum (2026-10-14):** Faz A (#244), B, C ve D (#245, #246) ve F (Çalışma Alanı'nda MCP
istemcisi: #297, #307, #309, #316, #324, #333, #334, #345) bitti; gerçek Claude bağlayıcısıyla
dev.pinegrap.com'da doğrulandı. Ayrıntı `docs/degisiklikler.md` → "OAuth 2.1
yetkilendirme sunucusu" ve sonraki 2026.4.10 bölümleri; kurallar
`.claude/skills/pinegrap-dis-api/SKILL.md`. Biten fazların bölümleri çıkarıldı; tam metin:
`git show d9fec3a:docs/_plan_oauth.md`. İstemci tarafı (Pinegrap'ın dış hesaplara bağlanması)
`docs/_plan_tersine_oauth.md`.

## Kalanlar

1. **Faz E — "Pinegrap ile giriş"** (OpenID Connect, `id_token`): başka yazılımlarda
   Pinegrap hesabıyla oturum açmak. Karar bekliyor, başlanmadı.
2. Faz F, isteğe bağlı: uzak ajan (proje notu `notes/mcp-arastirma.md`, üçüncü seviye).
   Görev notunda `@takmaad` ile sormak 2026-10-14'te bitti (#345, `docs/degisiklikler.md` →
   "Görev notunda @takmaad").

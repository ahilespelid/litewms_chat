// Swagger UI раздаём со своего домена (страница /docs не зависит от внешних CDN).
import { cpSync, mkdirSync } from 'node:fs';

const from = 'node_modules/swagger-ui-dist';
const to = 'public/vendor/swagger-ui';

mkdirSync(to, { recursive: true });
for (const file of ['swagger-ui.css', 'swagger-ui-bundle.js', 'swagger-ui-standalone-preset.js']) {
    cpSync(`${from}/${file}`, `${to}/${file}`);
}

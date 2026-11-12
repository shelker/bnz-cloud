import { cpSync, existsSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { dirname, join } from 'node:path'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const vendorSrc = join(root, 'vendor')
const vendorDest = join(root, 'dist', 'vendor')

if (!existsSync(join(vendorSrc, 'autoload.php'))) {
  console.error(
    '[postbuild] vendor/autoload.php fehlt. Bitte zuerst ausführen: composer install',
  )
  process.exit(1)
}

cpSync(vendorSrc, vendorDest, { recursive: true })
console.log('[postbuild] vendor → dist/vendor')

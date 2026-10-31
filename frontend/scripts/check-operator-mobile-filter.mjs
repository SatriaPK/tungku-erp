import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const [component, stylesheet, projectStylesheet] = await Promise.all([
  readFile(new URL('../src/features/manager/operators/ManagerOperatorsPage.tsx', import.meta.url), 'utf8'),
  readFile(new URL('../src/features/manager/operators/ManagerOperatorsPage.css', import.meta.url), 'utf8'),
  readFile(new URL('../src/features/manager/projects/ManagerProjectsPage.css', import.meta.url), 'utf8'),
])

const mobilePortal = stylesheet.match(/\.manager-operator-sort-portal\.manager-project-sort-layer\.is-mobile\s*\{([^}]*)\}/)
const projectMobilePortal = projectStylesheet.match(/\.manager-project-sort-layer\.is-mobile\s*\{([^}]*)\}/)
const mobileSheet = stylesheet.match(/\.manager-operator-sort-portal\s+\.manager-project-sort-popover\.is-mobile\s*\{([^}]*)\}/)

assert.ok(mobilePortal, 'operator sort layer must have a mobile viewport overlay rule')
assert.match(mobilePortal[1], /position:\s*fixed/)
assert.match(mobilePortal[1], /inset:\s*0/)
assert.ok(projectMobilePortal, 'project mobile filter reference should exist')
assert.match(projectMobilePortal[1], /display:\s*flex/)
assert.ok(mobileSheet, 'operator mobile sort modal must have its own sheet rule')
assert.match(mobileSheet[1], /position:\s*relative/)
assert.match(mobileSheet[1], /width:\s*100%/)
assert.match(mobileSheet[1], /max-height:\s*min\(70dvh,\s*480px\)/)

console.log('PASS: operator mobile filter renders as a full-viewport overlay with a bounded bottom sheet, matching Project')
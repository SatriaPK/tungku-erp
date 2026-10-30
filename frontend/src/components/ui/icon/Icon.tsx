import IconActivity from '~icons/lucide/activity'
import IconArrowDownRight from '~icons/lucide/arrow-down-right'
import IconArrowRight from '~icons/lucide/arrow-right'
import IconArrowUpRight from '~icons/lucide/arrow-up-right'
import IconBadgeCheck from '~icons/lucide/badge-check'
import IconBell from '~icons/lucide/bell'
import IconBox from '~icons/lucide/box'
import IconCalendarDays from '~icons/lucide/calendar-days'
import IconChartNoAxesCombined from '~icons/lucide/chart-no-axes-combined'
import IconCheck from '~icons/lucide/check'
import IconX from '~icons/lucide/x'
import IconChevronDown from '~icons/lucide/chevron-down'
import IconChevronLeft from '~icons/lucide/chevron-left'
import IconChevronRight from '~icons/lucide/chevron-right'
import IconChevronUp from '~icons/lucide/chevron-up'
import IconCirclePlus from '~icons/lucide/circle-plus'
import IconClock3 from '~icons/lucide/clock-3'
import IconCreditCard from '~icons/lucide/credit-card'
import IconDollarSign from '~icons/lucide/dollar-sign'
import IconDownload from '~icons/lucide/download'
import IconEye from '~icons/lucide/eye'
import IconEyeOff from '~icons/lucide/eye-off'
import IconEllipsisVertical from '~icons/lucide/ellipsis-vertical'
import IconPencil from '~icons/lucide/pencil'
import IconFileText from '~icons/lucide/file-text'
import IconFilter from '~icons/lucide/list-filter'
import IconFolder from '~icons/lucide/folder'
import IconGrid2x2 from '~icons/lucide/grid-2x2'
import IconHandshake from '~icons/lucide/handshake'
import IconHouse from '~icons/lucide/house'
import IconInventory from '~icons/lucide/clipboard-list'
import IconLogOut from '~icons/lucide/log-out'
import IconMenu from '~icons/lucide/menu'
import IconMic from '~icons/lucide/mic'
import IconMore from '~icons/lucide/ellipsis'
import IconMusic from '~icons/lucide/music-2'
import IconPackage from '~icons/lucide/package'
import IconPlus from '~icons/lucide/plus'
import IconPower from '~icons/lucide/power'
import IconSearch from '~icons/lucide/search'
import IconScissors from '~icons/lucide/scissors'
import IconSettings from '~icons/lucide/settings'
import IconSliders from '~icons/lucide/sliders-horizontal'
import IconSparkles from '~icons/lucide/sparkles'
import IconUserRound from '~icons/lucide/user-round'
import IconUsersRound from '~icons/lucide/users-round'
import type { ComponentType, SVGProps } from 'react'

const iconNames = [
  'activity', 'arrow-down-right', 'arrow-right', 'arrow-up-right', 'badge-check', 'bell', 'box', 'calendar',
  'chart', 'check', 'chevron-down', 'chevron-left', 'chevron-right', 'chevron-up', 'circle-plus', 'clock', 'close',
  'credit-card', 'dollar', 'download', 'edit', 'ellipsis-vertical', 'eye', 'eye-off', 'file', 'filter', 'folder', 'grid', 'handshake', 'home',
  'inventory', 'logout', 'menu', 'microphone', 'more', 'music', 'package', 'plus', 'power', 'search', 'scissors',
  'settings', 'sliders', 'sparkles', 'trending-down', 'trending-up', 'user', 'users',
] as const

export type IconName = (typeof iconNames)[number]

export interface IconProps extends SVGProps<SVGSVGElement> {
  name: IconName
  size?: number
}

type IconComponent = ComponentType<SVGProps<SVGSVGElement>>

const iconComponents = {
  activity: IconActivity,
  'arrow-down-right': IconArrowDownRight,
  'arrow-right': IconArrowRight,
  'arrow-up-right': IconArrowUpRight,
  'badge-check': IconBadgeCheck,
  bell: IconBell,
  box: IconBox,
  calendar: IconCalendarDays,
  chart: IconChartNoAxesCombined,
  check: IconCheck,
  close: IconX,
  'chevron-down': IconChevronDown,
  'chevron-left': IconChevronLeft,
  'chevron-right': IconChevronRight,
  'chevron-up': IconChevronUp,
  'circle-plus': IconCirclePlus,
  clock: IconClock3,
  'credit-card': IconCreditCard,
  dollar: IconDollarSign,
  download: IconDownload,
  eye: IconEye,
  'eye-off': IconEyeOff,
  edit: IconPencil,
  'ellipsis-vertical': IconEllipsisVertical,
  file: IconFileText,
  filter: IconFilter,
  folder: IconFolder,
  grid: IconGrid2x2,
  handshake: IconHandshake,
  home: IconHouse,
  inventory: IconInventory,
  logout: IconLogOut,
  menu: IconMenu,
  microphone: IconMic,
  more: IconMore,
  music: IconMusic,
  package: IconPackage,
  plus: IconPlus,
  power: IconPower,
  search: IconSearch,
  scissors: IconScissors,
  settings: IconSettings,
  sliders: IconSliders,
  sparkles: IconSparkles,
  'trending-down': IconArrowDownRight,
  'trending-up': IconArrowUpRight,
  user: IconUserRound,
  users: IconUsersRound,
} satisfies Record<IconName, IconComponent>

export function Icon({ name, size = 20, ...props }: IconProps) {
  const IconComponent = iconComponents[name]

  return <IconComponent aria-hidden="true" height={size} width={size} {...props} />
}
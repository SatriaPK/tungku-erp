import logo from '../../assets/client/rectangle1.png'
import more from '../../assets/client/more-vert.svg'
import search from '../../assets/client/search.svg'
import notifications from '../../assets/client/notifications.svg'
import recording from '../../assets/client/mic.svg'
import editing from '../../assets/client/cut.svg'
import mixing from '../../assets/client/mix.svg'
import mastering from '../../assets/client/graphic-eq.svg'
import recordingLarge from '../../assets/client/mic2.svg'
import editingLarge from '../../assets/client/cut2.svg'
import mixingLarge from '../../assets/client/mix2.svg'
import masteringLarge from '../../assets/client/graphic-eq2.svg'
import minus from '../../assets/client/minus.svg'
import add from '../../assets/client/add.svg'
import filter from '../../assets/client/svg.svg'
import toggle from '../../assets/client/toggle-button.svg'
import separator from '../../assets/client/separator.svg'

export const assets = { logo, more, search, notifications, minus, add, filter, toggle, separator }
export const stages = [
  { key: 'recording', name: 'Recording', icon: recording, largeIcon: recordingLarge, unit: 'Jam' },
  { key: 'editing', name: 'Editing', icon: editing, largeIcon: editingLarge, unit: 'Lagu' },
  { key: 'mixing', name: 'Mixing', icon: mixing, largeIcon: mixingLarge, unit: 'Lagu' },
  { key: 'mastering', name: 'Mastering', icon: mastering, largeIcon: masteringLarge, unit: 'Lagu' },
] as const

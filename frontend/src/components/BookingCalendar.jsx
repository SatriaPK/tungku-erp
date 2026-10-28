import { useState } from "react";
import { ChevronLeft, ChevronRight } from "lucide-react";

const dateKey = (date) =>
  new Intl.DateTimeFormat("en-CA", {
    timeZone: "Asia/Jakarta",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(date);
const atNoon = (key) => new Date(`${key}T12:00:00+07:00`);
const move = (date, days) => new Date(date.getTime() + days * 86400000);
const title = (row) =>
  row.project_name ||
  row.project?.name ||
  row.room?.name ||
  `Booking #${row.id}`;
const time = (value) =>
  new Intl.DateTimeFormat("id-ID", {
    hour: "2-digit",
    minute: "2-digit",
    timeZone: "Asia/Jakarta",
    hourCycle: "h23",
  }).format(new Date(value));
export default function BookingCalendar({ rows, onOpen }) {
  const [selected, setSelected] = useState(() => dateKey(new Date()));
  const date = atNoon(selected);
  const month = new Date(date);
  month.setDate(1);
  const calendarStart = move(month, -month.getDay());
  const weekStart = move(date, -date.getDay());
  const days = Array.from({ length: 7 }, (_, i) => move(weekStart, i));
  const dayStart = (day) =>
    new Date(`${dateKey(day)}T00:00:00+07:00`).getTime();
  const sessions = (day) =>
    rows
      .filter(
        (row) =>
          row.start_at &&
          row.end_at &&
          Date.parse(row.start_at) < dayStart(day) + 86400000 &&
          Date.parse(row.end_at) > dayStart(day),
      )
      .sort((a, b) => Date.parse(a.start_at) - Date.parse(b.start_at));
  const visible = days.flatMap((day) =>
    sessions(day).map((row) => ({
      start: Math.max(0, (Date.parse(row.start_at) - dayStart(day)) / 60000),
      end: Math.min(1440, (Date.parse(row.end_at) - dayStart(day)) / 60000),
    })),
  );
  const startHour = Math.min(
    9,
    ...visible.map((row) => Math.floor(row.start / 60)),
  );
  const endHour = Math.max(
    22,
    ...visible.map((row) => Math.ceil(row.end / 60)),
  );
  const calendarHeight = (endHour - startHour) * 120;
  function positions(day) {
    const lanes = [];
    const positioned = sessions(day).map((row) => {
      const start = Math.max(
        0,
        (Date.parse(row.start_at) - dayStart(day)) / 60000,
      );
      const end = Math.min(
        1440,
        (Date.parse(row.end_at) - dayStart(day)) / 60000,
      );
      let lane = lanes.findIndex((until) => until <= start);
      if (lane < 0) lane = lanes.length;
      lanes[lane] = end;
      return { row, start, end, lane };
    });
    return positioned.map((item) => ({
      ...item,
      style: {
        top: `${(item.start - startHour * 60) * 2}px`,
        height: `${Math.max(48, (item.end - item.start) * 2 - 8)}px`,
        left: `calc(${(item.lane / lanes.length) * 100}% + 4px)`,
        width: `calc(${100 / lanes.length}% - 8px)`,
      },
    }));
  }
  const changeMonth = (delta) => {
    const next = new Date(date);
    next.setDate(1);
    next.setMonth(next.getMonth() + delta);
    setSelected(dateKey(next));
  };
  const monthLabel = new Intl.DateTimeFormat("id-ID", {
    month: "long",
    year: "numeric",
    timeZone: "Asia/Jakarta",
  }).format(date);
  return (
    <div className="booking-calendar-layout">
      <aside className="panel calendar-sidebar">
        <header>
          <strong>{monthLabel}</strong>
          <div>
            <button
              className="icon-button"
              aria-label="Bulan sebelumnya"
              onClick={() => changeMonth(-1)}
            >
              <ChevronLeft size={18} />
            </button>
            <button
              className="icon-button"
              aria-label="Bulan berikutnya"
              onClick={() => changeMonth(1)}
            >
              <ChevronRight size={18} />
            </button>
          </div>
        </header>
        <div className="mini-calendar">
          {["M", "S", "S", "R", "K", "J", "S"].map((label, i) => (
            <small key={i}>{label}</small>
          ))}
          {Array.from({ length: 42 }, (_, i) => move(calendarStart, i)).map(
            (day) => (
              <button
                key={dateKey(day)}
                className={`${dateKey(day) === selected ? "selected" : ""} ${day.getMonth() !== date.getMonth() ? "outside" : ""} ${sessions(day).length ? "has-booking" : ""}`}
                onClick={() => setSelected(dateKey(day))}
                aria-label={new Intl.DateTimeFormat("id-ID", {
                  dateStyle: "full",
                  timeZone: "Asia/Jakarta",
                }).format(day)}
              >
                {day.getDate()}
              </button>
            ),
          )}
        </div>
        <h3>Jadwal Minggu Ini</h3>
        {days.map(
          (day) =>
            sessions(day).length > 0 && (
              <div className="day-agenda" key={dateKey(day)}>
                <small>
                  {new Intl.DateTimeFormat("id-ID", {
                    dateStyle: "full",
                    timeZone: "Asia/Jakarta",
                  }).format(day)}
                </small>
                {sessions(day).map((row) => (
                  <button key={row.id} onClick={() => onOpen(row)}>
                    <strong>{title(row)}</strong>
                    <span>{row.client?.name || row.client_name}</span>
                    <small>
                      {time(row.start_at)} – {time(row.end_at)}
                    </small>
                  </button>
                ))}
              </div>
            ),
        )}
        {!days.some((day) => sessions(day).length) && (
          <p className="muted">
            Belum ada jadwal pada minggu ini di halaman data ini.
          </p>
        )}
      </aside>
      <section className="panel calendar-week">
        <header>
          <h1>{monthLabel}</h1>
          <div>
            <button
              className="icon-button"
              aria-label="Minggu sebelumnya"
              onClick={() => setSelected(dateKey(move(date, -7)))}
            >
              <ChevronLeft size={18} />
            </button>
            <button onClick={() => setSelected(dateKey(new Date()))}>
              Hari Ini
            </button>
            <button
              className="icon-button"
              aria-label="Minggu berikutnya"
              onClick={() => setSelected(dateKey(move(date, 7)))}
            >
              <ChevronRight size={18} />
            </button>
          </div>
        </header>
        <div className="week-scroll">
          <div
            className="week-grid"
            style={{ "--calendar-height": `${calendarHeight}px` }}
          >
            <div className="week-timezone">UTC+7</div>
            {days.map((day) => (
              <div
                className={`week-day-title ${dateKey(day) === selected ? "selected" : ""}`}
                key={dateKey(day)}
              >
                {new Intl.DateTimeFormat("id-ID", {
                  weekday: "short",
                  day: "numeric",
                  timeZone: "Asia/Jakarta",
                }).format(day)}
              </div>
            ))}
            <div className="week-hours">
              {Array.from({ length: endHour - startHour }, (_, i) => (
                <span key={i} style={{ top: `${i * 120}px` }}>
                  {String(startHour + i).padStart(2, "0")}:00
                </span>
              ))}
            </div>
            {days.map((day) => (
              <div className="week-day-column" key={dateKey(day)}>
                {sessions(day).length ? (
                  positions(day).map(({ row, style }, i) => (
                    <button
                      style={style}
                      className={`calendar-session tone-${i % 4}`}
                      key={row.id}
                      onClick={() => onOpen(row)}
                    >
                      <small>
                        {time(row.start_at)} – {time(row.end_at)}
                      </small>
                      <strong>{title(row)}</strong>
                      <span>{row.client?.name || row.client_name}</span>
                      <small>{row.status_label}</small>
                    </button>
                  ))
                ) : (
                  <span className="calendar-free">Kosong</span>
                )}
              </div>
            ))}
          </div>
        </div>
        <small className="muted">
          Jadwal ditampilkan dalam WIB dari data API pada halaman ini. Pilih
          jadwal untuk melihat detail.
        </small>
      </section>
    </div>
  );
}

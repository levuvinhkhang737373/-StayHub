const DASHBOARD_STATS = [
  { label: 'Phòng đang thuê', value: '128', change: '+12%', tone: 'from-[#24170d] to-[#a65f16]' },
  { label: 'Khách thuê', value: '246', change: '+18', tone: 'from-[#0f766e] to-[#f3c56b]' },
  { label: 'Doanh thu tháng', value: '486M', change: '+8.4%', tone: 'from-[#a65f16] to-[#f3c56b]' },
  { label: 'Yêu cầu bảo trì', value: '17', change: '5 mới', tone: 'from-rose-700 to-[#a65f16]' },
]

const RECENT_ACTIVITIES = [
  'Hợp đồng A-204 vừa được gia hạn thêm 12 tháng.',
  'Phòng B-503 đã hoàn tất thanh toán kỳ tháng này.',
  'Có 3 yêu cầu bảo trì đang chờ phân công nhân sự.',
  'Tòa C cập nhật chỉ số điện nước thành công.',
]

export function AdminDashboardScreen() {
  return (
    <section className="space-y-6 text-[#24170d]">
      <div className="relative overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#24170d] p-5 text-[#fff4df] shadow-2xl shadow-[#6b3f1d]/18 sm:p-6">
        <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_18%_12%,rgba(243,197,107,0.26),transparent_28%),radial-gradient(circle_at_86%_22%,rgba(15,118,110,0.26),transparent_30%),linear-gradient(135deg,#24170d_0%,#3d2a18_48%,#0f3f3b_100%)]" />
        <div className="pointer-events-none absolute inset-0 opacity-[0.12] [background-image:linear-gradient(90deg,rgba(248,232,200,0.28)_1px,transparent_1px),linear-gradient(rgba(248,232,200,0.18)_1px,transparent_1px)] [background-size:72px_72px]" />
        <div className="relative max-w-3xl space-y-2">
          <p className="text-xs font-black uppercase tracking-[0.24em] text-[#f3c56b]">StayHub Admin</p>
          <h1 className="text-2xl font-black tracking-tight sm:text-3xl lg:text-4xl">Tổng quan vận hành ký túc xá</h1>
          <p className="max-w-2xl text-sm font-semibold leading-5 text-[#f8e8c8]/82">
            Theo dõi tình trạng phòng, khách thuê, doanh thu và các yêu cầu vận hành trong cùng một bảng điều khiển.
          </p>
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        {DASHBOARD_STATS.map((stat) => (
          <article key={stat.label} className="rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-5 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md transition duration-200 hover:-translate-y-1 hover:bg-[#fff7e8] hover:shadow-2xl hover:shadow-[#6b3f1d]/14">
            <div className={`mb-5 h-12 w-12 rounded-2xl bg-gradient-to-br ${stat.tone} shadow-lg shadow-[#6b3f1d]/18`} />
            <p className="text-sm font-bold text-[#6f6254]">{stat.label}</p>
            <div className="mt-2 flex items-end justify-between gap-3">
              <strong className="text-3xl font-black text-[#24170d]">{stat.value}</strong>
              <span className="rounded-full border border-[#0f766e]/10 bg-[#0f766e]/10 px-3 py-1 text-xs font-black text-[#0f5f59]">{stat.change}</span>
            </div>
          </article>
        ))}
      </div>

      <div className="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
        <div className="rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-6 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
          <div className="mb-6 flex items-center justify-between gap-4">
            <div>
              <h2 className="text-xl font-black text-[#24170d]">Hiệu suất doanh thu</h2>
              <p className="text-sm font-semibold text-[#6f6254]">Dữ liệu mẫu để kiểm tra Tailwind CSS đã hoạt động đầy đủ.</p>
            </div>
            <span className="rounded-full border border-[#a65f16]/10 bg-[#f3c56b]/18 px-4 py-2 text-xs font-black text-[#8a4f18]">Tháng 04/2026</span>
          </div>
          <div className="flex h-72 items-end gap-3 rounded-[1.5rem] border border-[#3d2a18]/8 bg-[#fff7e8]/78 p-5 shadow-inner shadow-[#6b3f1d]/6">
            {[42, 58, 51, 72, 66, 84, 76, 91, 88, 96, 89, 100].map((height, index) => (
              <div key={index} className="flex flex-1 flex-col items-center gap-2">
                <div className="w-full rounded-t-2xl bg-gradient-to-t from-[#a65f16] via-[#f3c56b] to-[#0f766e] shadow-sm shadow-[#6b3f1d]/12" style={{ height: `${height}%` }} />
                <span className="text-[10px] font-bold text-[#8b5e34]/60">T{index + 1}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="rounded-3xl border border-[#3d2a18]/10 bg-[#fffaf1]/82 p-6 shadow-lg shadow-[#6b3f1d]/8 backdrop-blur-md">
          <h2 className="text-xl font-black text-[#24170d]">Hoạt động gần đây</h2>
          <div className="mt-5 space-y-4">
            {RECENT_ACTIVITIES.map((activity) => (
              <div key={activity} className="rounded-2xl border border-[#3d2a18]/8 bg-[#fff7e8]/82 p-4 text-sm font-semibold leading-6 text-[#3d2a18] shadow-sm shadow-[#6b3f1d]/5">
                {activity}
              </div>
            ))}
          </div>
        </div>
      </div>
    </section>
  )
}

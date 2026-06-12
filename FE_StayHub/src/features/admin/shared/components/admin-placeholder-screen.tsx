import { ArrowLeft, Sparkles } from 'lucide-react'
import { Link } from 'react-router-dom'

type AdminPlaceholderScreenProps = {
  title: string
  description: string
}

export function AdminPlaceholderScreen({ title, description }: AdminPlaceholderScreenProps) {
  return (
    <section className="flex min-h-[520px] items-center justify-center p-0">
        <section className="w-full max-w-2xl overflow-hidden rounded-[2rem] border border-[#3d2a18]/10 bg-[#fffaf1]/90 shadow-2xl shadow-[#6b3f1d]/12 backdrop-blur-md">
          <div className="border-b border-[#3d2a18]/10 bg-[#24170d] p-6 text-[#fff4df]">
            <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-[#f8e8c8]/15 bg-[#f8e8c8]/10 text-[#f3c56b]">
              <Sparkles className="h-6 w-6" />
            </div>
            <h1 className="text-3xl font-black tracking-[-0.04em] sm:text-4xl">{title}</h1>
            <p className="mt-3 text-sm font-semibold leading-6 text-[#f8e8c8]/78">{description}</p>
          </div>

          <div className="p-6">
            <p className="text-sm font-bold leading-6 text-[#6f6254]">Mục này đã được mở trên thanh điều hướng. Giao diện chức năng chi tiết sẽ được triển khai ở bước tiếp theo.</p>
            <Link to="/admin/dashboard" className="mt-6 inline-flex min-h-12 items-center justify-center gap-2 rounded-2xl bg-[#24170d] px-5 text-sm font-black uppercase tracking-widest text-[#fff4df] shadow-xl shadow-[#6b3f1d]/18 transition hover:bg-[#3d2a18] focus:outline-none focus:ring-4 focus:ring-[#f3c56b]/30">
              <ArrowLeft className="h-4 w-4 text-[#f3c56b]" />
              Quay lại tổng quan
            </Link>
          </div>
        </section>
      </section>
  )
}

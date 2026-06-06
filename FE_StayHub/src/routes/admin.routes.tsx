import type { RouteObject } from 'react-router-dom'
import { Navigate } from 'react-router-dom'
import { AdminLoginScreen } from '../features/admin/auth/components/admin-login-screen'
import { AdminDashboardScreen } from '../features/admin/dashboard/components/admin-dashboard-screen'
import { AssetTemplatesScreen } from '../features/admin/asset-templates/components/asset-templates-screen'
import { CreateBuildingScreen } from '../features/admin/facilities/components/create-building-screen'
import { RoomTypesScreen } from '../features/admin/room-types/components/room-types-screen'
import { ExpenseCategoriesScreen } from '../features/admin/expense-categories/components/expense-categories-screen'
import { MetersScreen } from '../features/admin/meters/components/meters-screen'
import { ServicesScreen } from '../features/admin/services/components/services-screen'
import { SettingsScreen } from '../features/admin/settings/components/settings-screen'
import { SystemUsersScreen } from '../features/admin/system-users'
import { TenantsScreen } from '../features/admin/tenants'
import { CreateRegionScreen } from '../features/admin/facilities/components/create-region-screen'
import { FacilitiesScreen } from '../features/admin/facilities/components/facilities-screen'
import { AdminPlaceholderScreen } from '../features/admin/shared/components/admin-placeholder-screen'
import { AdminRouteGuard } from '../features/admin/shared/components/AdminRouteGuard'
import { AdminLayout } from '../layouts/admin/AdminLayout'

export const adminRoutes: RouteObject[] = [
  {
    path: 'admin/login',
    element: <AdminLoginScreen />,
  },
  {
    path: 'admin',
    element: <AdminLayout />,
    children: [
      {
        index: true,
        element: <Navigate to="/admin/dashboard" replace />,
      },
      {
        path: 'dashboard',
        element: <AdminDashboardScreen />,
      },
      {
        path: 'facilities',
        element: <AdminRouteGuard access="superadmin"><FacilitiesScreen /></AdminRouteGuard>,
      },
      {
        path: 'facilities/regions/create',
        element: <AdminRouteGuard access="superadmin"><CreateRegionScreen /></AdminRouteGuard>,
      },
      {
        path: 'facilities/regions/:regionId/edit',
        element: <AdminRouteGuard access="superadmin"><CreateRegionScreen /></AdminRouteGuard>,
      },
      {
        path: 'facilities/buildings/create',
        element: <AdminRouteGuard access="superadmin"><CreateBuildingScreen /></AdminRouteGuard>,
      },
      {
        path: 'facilities/buildings/:buildingId/edit',
        element: <AdminRouteGuard access="superadmin"><CreateBuildingScreen /></AdminRouteGuard>,
      },
      {
        path: 'asset-templates',
        element: <AssetTemplatesScreen />,
      },
      {
        path: 'room-types',
        element: <RoomTypesScreen />,
      },
      {
        path: 'rooms',
        element: <AdminPlaceholderScreen title="Quản lý Phòng" description="Quản lý danh sách phòng, trạng thái phòng và cấu hình vận hành phòng." />,
      },
      {
        path: 'tenants',
        element: <TenantsScreen />,
      },
      {
        path: 'contracts',
        element: <AdminPlaceholderScreen title="Hợp đồng" description="Theo dõi hợp đồng thuê, thời hạn, phụ lục và trạng thái hiệu lực." />,
      },
      {
        path: 'services',
        element: <AdminRouteGuard access="superadmin"><ServicesScreen /></AdminRouteGuard>,
      },
      {
        path: 'expense-categories',
        element: <ExpenseCategoriesScreen />,
      },
      {
        path: 'meters',
        element: <MetersScreen />,
      },
      {
        path: 'invoices',
        element: <AdminPlaceholderScreen title="Phiếu thu" description="Quản lý hóa đơn, khoản thu và tình trạng thanh toán của khách thuê." />,
      },
      {
        path: 'expenses',
        element: <AdminPlaceholderScreen title="Phiếu chi" description="Theo dõi chi phí vận hành, bảo trì và các khoản chi của hệ thống." />,
      },
      {
        path: 'financials',
        element: <AdminPlaceholderScreen title="Báo cáo Lợi nhuận" description="Tổng hợp doanh thu, chi phí và lợi nhuận theo khu vực hoặc tòa nhà." />,
      },
      {
        path: 'vehicles',
        element: <AdminPlaceholderScreen title="Bãi xe & Phương tiện" description="Quản lý phương tiện, bãi xe, phí gửi xe và trạng thái đăng ký." />,
      },
      {
        path: 'maintenance',
        element: <AdminPlaceholderScreen title="Bảo trì" description="Tiếp nhận, phân công và theo dõi tiến độ xử lý yêu cầu bảo trì." />,
      },
      {
        path: 'notifications',
        element: <AdminPlaceholderScreen title="Thông báo" description="Tạo và quản lý thông báo gửi tới khách thuê hoặc nhân sự vận hành." />,
      },
      {
        path: 'system-users',
        element: <AdminRouteGuard access="superadmin"><SystemUsersScreen /></AdminRouteGuard>,
      },
      {
        path: 'settings',
        element: <SettingsScreen />,
      },
    ],
  },
]

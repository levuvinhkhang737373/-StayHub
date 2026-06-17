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
import { ContractsScreen, CreateContractScreen } from '../features/admin/contracts'
import { InvoicesScreen } from '../features/admin/invoices'
import { CreateTenantScreen } from '../features/admin/tenants/components/create-tenant-screen'
import { FacilitiesScreen } from '../features/admin/facilities/components/facilities-screen'
import { AdminPlaceholderScreen } from '../features/admin/shared/components/admin-placeholder-screen'
import { AdminRouteGuard } from '../features/admin/shared/components/AdminRouteGuard'
import { AdminLayout } from '../layouts/admin/AdminLayout'
import { RoomsScreen } from '../features/admin/rooms/components/rooms-screen'
import { CreateRoomScreen } from '../features/admin/rooms/components/create-room-screen'
import { UpdateRoomScreen } from '../features/admin/rooms/components/update-room-screen'
import { MaintenanceScreen } from '../features/admin/maintenance'
import { NotificationsScreen } from '../features/admin/notifications'
import { VehiclesScreen } from '../features/admin/vehicles'

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
        path: 'facilities/buildings/create',
        element: <AdminRouteGuard access="superadmin"><CreateBuildingScreen /></AdminRouteGuard>,
      },
      {
        path: 'facilities/buildings/:buildingId/edit',
        element: <AdminRouteGuard access="superadmin"><CreateBuildingScreen /></AdminRouteGuard>,
      },
      {
        path: 'asset-templates',
        element: <AdminRouteGuard access="superadmin"><AssetTemplatesScreen /></AdminRouteGuard>,
      },
      {
        path: 'room-types',
        element: <AdminRouteGuard access="superadmin"><RoomTypesScreen /></AdminRouteGuard>,
      },
      {
        path: 'rooms',
        element: <AdminRouteGuard access='all'><RoomsScreen /></AdminRouteGuard>,
      },
      {
        path: 'rooms/create',
        element: <AdminRouteGuard access='all'><CreateRoomScreen /></AdminRouteGuard>,
      },
      {
        path: 'rooms/update/:id',
        element: <AdminRouteGuard access='all'><UpdateRoomScreen /></AdminRouteGuard>,
      },
      {
        path: 'tenants',
        element: <TenantsScreen />,
      },
      {
        path: 'tenants/create',
        element: <CreateTenantScreen />,
      },
      {
        path: 'tenants/:tenantId/edit',
        element: <CreateTenantScreen />,
      },
      {
        path: 'contracts',
        element: <AdminRouteGuard access="contract-manager"><ContractsScreen /></AdminRouteGuard>,
      },
      {
        path: 'contracts/create',
        element: <AdminRouteGuard access="contract-manager"><CreateContractScreen /></AdminRouteGuard>,
      },
      {
        path: 'contracts/:contractId/edit',
        element: <AdminRouteGuard access="contract-manager"><CreateContractScreen /></AdminRouteGuard>,
      },
      {
        path: 'contracts/:contractId/renew',
        element: <AdminRouteGuard access="contract-manager"><CreateContractScreen /></AdminRouteGuard>,
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
        path: 'meter-readings',
        element: <AdminPlaceholderScreen title="Chốt điện nước" description="Chốt chỉ số điện nước, tính toán hóa đơn và quản lý chu kỳ thanh toán." />,
      },
      {
        path: 'invoices',
        element: <AdminRouteGuard access="all"><InvoicesScreen /></AdminRouteGuard>,
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
        element: <VehiclesScreen />,
      },
      {
        path: 'maintenance',
        element: <MaintenanceScreen />,
      },
      {
        path: 'notifications',
        element: <NotificationsScreen />,
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

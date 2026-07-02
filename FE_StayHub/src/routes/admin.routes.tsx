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
import { SystemUsersScreen, CreateSystemUserScreen } from '../features/admin/system-users'
import { TenantsScreen } from '../features/admin/tenants'
import { ContractsScreen, CreateContractScreen } from '../features/admin/contracts'
import { InvoicesScreen } from '../features/admin/invoices'
import { ExpensesScreen } from '../features/admin/expenses'
import { CreateTenantScreen } from '../features/admin/tenants/components/create-tenant-screen'
import { FacilitiesScreen } from '../features/admin/facilities/components/facilities-screen'
import { FinancialsScreen } from '../features/admin/financials/components/financials-screen'
import { PaymentHistoryScreen } from '../features/admin/payment-history'
import { AdminRouteGuard } from '../features/admin/shared/components/AdminRouteGuard'
import { AdminLayout } from '../layouts/admin/AdminLayout'
import { RoomsScreen } from '../features/admin/rooms/components/rooms-screen'
import { CreateRoomScreen } from '../features/admin/rooms/components/create-room-screen'
import { UpdateRoomScreen } from '../features/admin/rooms/components/update-room-screen'
import { MaintenanceScreen } from '../features/admin/maintenance'
import { NotificationsScreen } from '../features/admin/notifications'
import { VehiclesScreen } from '../features/admin/vehicles'
import { MeterReadingsScreen } from '../features/admin/meter-readings'
import { TenantTransferRoomScreen } from '../features/admin/tenants/components/TenantTransferRoomScreen'
import { ActivityLogsScreen } from '../features/admin/activity-logs'
import { RoomMovementsScreen } from '../features/admin/room-movements'
import { AdminChatScreen } from '../features/admin/chat'
import { FireSafetyScreen } from '../features/admin/fire-safety'
import { LegacyTenantTransferRoomRedirect } from './LegacyTenantTransferRoomRedirect'

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
        path: 'transfer-room',
        element: <AdminRouteGuard access="all"><TenantTransferRoomScreen /></AdminRouteGuard>,
      },
      {
        path: 'tenants/:tenantId/transfer-room',
        element: <LegacyTenantTransferRoomRedirect />,
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
        path: 'room-movements',
        element: <AdminRouteGuard access="all"><RoomMovementsScreen /></AdminRouteGuard>,
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
        element: <AdminRouteGuard access="all"><MetersScreen /></AdminRouteGuard>,
      },
      {
        path: 'meter-readings',
        element: <AdminRouteGuard access="all"><MeterReadingsScreen /></AdminRouteGuard>,
      },
      {
        path: 'invoices',
        element: <AdminRouteGuard access="all"><InvoicesScreen /></AdminRouteGuard>,
      },
      {
        path: 'expenses',
        element: <AdminRouteGuard access="all"><ExpensesScreen /></AdminRouteGuard>,
      },
      {
        path: 'payment-history',
        element: <AdminRouteGuard access="all"><PaymentHistoryScreen /></AdminRouteGuard>,
      },
      {
        path: 'financials',
        element: <FinancialsScreen />,
      },
      {
        path: 'vehicles',
        element: <VehiclesScreen />,
      },
      {
        path: 'fire-safety',
        element: <AdminRouteGuard access="all"><FireSafetyScreen /></AdminRouteGuard>,
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
        path: 'chat',
        element: <AdminRouteGuard access="all"><AdminChatScreen /></AdminRouteGuard>,
      },
      {
        path: 'system-users',
        element: <AdminRouteGuard access="superadmin"><SystemUsersScreen /></AdminRouteGuard>,
      },
      {
        path: 'system-users/create',
        element: <AdminRouteGuard access="superadmin"><CreateSystemUserScreen /></AdminRouteGuard>,
      },
      {
        path: 'system-users/update/:id',
        element: <AdminRouteGuard access="superadmin"><CreateSystemUserScreen /></AdminRouteGuard>,
      },
      {
        path: 'activity-logs',
        element: <AdminRouteGuard access="superadmin"><ActivityLogsScreen /></AdminRouteGuard>,
      },
      {
        path: 'settings',
        element: <SettingsScreen />,
      },
    ],
  },
]

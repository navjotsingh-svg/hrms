<script>

    window.HRMS_WEB_ROUTES = {

        login: @json(route('login')),

        dashboard: @json(route('web.dashboard')),

        profile: @json(route('web.profile')),

        companiesIndex: @json(route('web.companies.index')),

        companiesCreate: @json(route('web.companies.create')),

        companyShow: @json(url('/companies')),

        companyEdit: @json(url('/companies')),

        departmentsIndex: @json(route('web.masters.departments.index')),

        departmentCreate: @json(route('web.masters.departments.create')),

        departmentEdit: @json(url('/masters/departments')),

        documentsIndex: @json(route('web.masters.documents.index')),

        documentCreate: @json(route('web.masters.documents.create')),

        documentEdit: @json(url('/masters/documents')),

        assetsIndex: @json(route('web.masters.assets.index')),

        assetCreate: @json(route('web.masters.assets.create')),

        assetEdit: @json(url('/masters/assets')),

        shiftsIndex: @json(route('web.masters.shifts.index')),

        shiftCreate: @json(route('web.masters.shifts.create')),

        shiftEdit: @json(url('/masters/shifts')),

        rolesIndex: @json(route('web.masters.roles.index')),

        roleShow: @json(url('/masters/roles')),

        peopleIndex: @json(route('web.people.index')),

        employeesIndex: @json(route('web.employees.index')),

        employeeCreate: @json(route('web.employees.create')),

        employeeEdit: @json(url('/employees')),

        employeeShow: @json(url('/employees')),

        attendanceIndex: @json(route('web.attendance.index')),

        attendanceOverview: @json(route('web.attendance.overview')),

        attendanceRegularizeIndex: @json(route('web.attendance.regularize.index')),

        holidaysIndex: @json(route('web.masters.attendance.holidays.index')),

        holidayCreate: @json(route('web.masters.attendance.holidays.create')),

        holidayEdit: @json(url('/masters/attendance/holidays')),

        weeklyOffIndex: @json(route('web.masters.attendance.weekly-off.index')),

        portalStartIndex: @json(route('web.masters.attendance.portal-start.index')),

        leaveIndex: @json(route('web.leave.index')),

        leaveApply: @json(route('web.leave.apply')),

        leaveBalances: @json(route('web.leave.balances')),

        leaveManageBalances: @json(route('web.leave.manage-balances')),

        leaveShow: @json(url('/leave')),

        requestsIndex: @json(route('web.requests.index')),

        projectsIndex: @json(route('web.projects.index')),

        timesheetsIndex: @json(route('web.timesheets.index')),

        leaveTypesIndex: @json(route('web.masters.leave-types.index')),

        leaveTypeEdit: @json(url('/masters/leave-types')),

        payrollIndex: @json(route('web.payroll.index')),

        myPayslips: @json(route('web.payroll.my-payslips')),

    };

</script>


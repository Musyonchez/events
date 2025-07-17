import { requestWithAuth } from '../http.js';
import { isAuthenticated, getCurrentUser } from '../auth.js';

// Admin Statistics and Analytics Module
export class AdminStats {
    constructor() {
        this.charts = {};
        this.refreshInterval = null;
    }

    // Initialize analytics dashboard
    async initialize() {
        if (!this.checkAdminAccess()) {
            return false;
        }

        await this.loadAnalyticsData();
        this.setupAutoRefresh();
        return true;
    }

    checkAdminAccess() {
        if (!isAuthenticated()) {
            return false;
        }

        const user = getCurrentUser();
        return user && (user.role === 'admin' || user.role === 'club_leader');
    }

    // Load all analytics data
    async loadAnalyticsData() {
        try {
            const [statsData, chartData, activityData] = await Promise.all([
                this.loadDashboardStats(),
                this.loadChartData(),
                this.loadRecentActivity()
            ]);

            this.updateDashboardStats(statsData);
            this.updateCharts(chartData);
            this.updateRecentActivity(activityData);

        } catch (error) {
            console.error('Error loading analytics data:', error);
            this.showErrorMessage('Failed to load analytics data');
        }
    }

    // Load dashboard statistics
    async loadDashboardStats() {
        try {
            const response = await requestWithAuth('/admin/stats.php?type=dashboard', 'GET');
            return response.data || {};
        } catch (error) {
            console.error('Error loading dashboard stats:', error);
            return {};
        }
    }

    // Load chart data
    async loadChartData() {
        try {
            const response = await requestWithAuth('/admin/stats.php?type=charts', 'GET');
            return response.data || {};
        } catch (error) {
            console.error('Error loading chart data:', error);
            return {};
        }
    }

    // Load recent activity
    async loadRecentActivity() {
        try {
            const response = await requestWithAuth('/admin/activity.php?limit=20', 'GET');
            return response.data?.activities || [];
        } catch (error) {
            console.error('Error loading recent activity:', error);
            return [];
        }
    }

    // Update dashboard statistics
    updateDashboardStats(stats) {
        const statsMap = {
            'total-events': stats.total_events || 0,
            'total-users': stats.total_users || 0,
            'active-clubs': stats.active_clubs || 0,
            'events-this-month': stats.events_this_month || 0,
            'new-users-month': stats.new_users_month || 0,
            'active-users': stats.active_users || 0,
            'avg-attendance': `${stats.avg_attendance || 0}%`,
            'verification-rate': `${stats.verification_rate || 0}%`,
            'popular-category': stats.popular_category || 'N/A'
        };

        // Update revenue with formatting
        if (stats.total_revenue !== undefined) {
            const revenueElement = document.getElementById('total-revenue');
            if (revenueElement) {
                revenueElement.textContent = `KSh ${(stats.total_revenue || 0).toLocaleString()}`;
            }
        }

        // Update all other stats
        Object.entries(statsMap).forEach(([elementId, value]) => {
            const element = document.getElementById(elementId);
            if (element) {
                element.textContent = value;
            }
        });
    }

    // Update charts with new data
    updateCharts(chartData) {
        // Events chart
        if (chartData.events_over_time) {
            this.updateEventsChart(chartData.events_over_time);
        }

        // Users chart
        if (chartData.users_over_time) {
            this.updateUsersChart(chartData.users_over_time);
        }

        // Categories chart
        if (chartData.events_by_category) {
            this.updateCategoriesChart(chartData.events_by_category);
        }

        // Registration trends
        if (chartData.registration_trends) {
            this.updateRegistrationChart(chartData.registration_trends);
        }
    }

    // Create or update events chart
    updateEventsChart(data) {
        const ctx = document.getElementById('events-chart');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (this.charts.events) {
            this.charts.events.destroy();
        }

        this.charts.events = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Events Created',
                    data: data.values || [],
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Events Created Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Create or update users chart
    updateUsersChart(data) {
        const ctx = document.getElementById('users-chart');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (this.charts.users) {
            this.charts.users.destroy();
        }

        this.charts.users = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'New Users',
                    data: data.values || [],
                    backgroundColor: 'rgba(34, 197, 94, 0.8)',
                    borderColor: 'rgb(34, 197, 94)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'User Registrations Over Time'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Create or update categories chart
    updateCategoriesChart(data) {
        const ctx = document.getElementById('categories-chart');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (this.charts.categories) {
            this.charts.categories.destroy();
        }

        this.charts.categories = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels || [],
                datasets: [{
                    data: data.values || [],
                    backgroundColor: [
                        '#3B82F6', '#EF4444', '#10B981', '#F59E0B',
                        '#8B5CF6', '#F97316', '#06B6D4', '#84CC16'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Events by Category'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Create or update registration trends chart
    updateRegistrationChart(data) {
        const ctx = document.getElementById('registration-chart');
        if (!ctx) return;

        // Destroy existing chart if it exists
        if (this.charts.registrations) {
            this.charts.registrations.destroy();
        }

        this.charts.registrations = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Event Registrations',
                    data: data.values || [],
                    borderColor: 'rgb(168, 85, 247)',
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: true,
                        text: 'Event Registrations Trend'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    }

    // Update recent activity list
    updateRecentActivity(activities) {
        const container = document.getElementById('recent-activity');
        if (!container) return;

        if (activities.length === 0) {
            container.innerHTML = this.createEmptyState('No recent activity', 'No recent activity to display.');
            return;
        }

        container.innerHTML = activities.map(activity => this.createActivityItem(activity)).join('');
    }

    // Create activity item HTML
    createActivityItem(activity) {
        const activityIcons = {
            user_registered: '👤',
            event_created: '📅',
            event_registration: '✅',
            club_created: '🏛️',
            admin_action: '⚙️',
            event_updated: '📝',
            club_updated: '🔄',
            user_suspended: '🚫',
            user_activated: '✅'
        };

        const timeAgo = this.getTimeAgo(activity.created_at);

        return `
            <div class="px-6 py-4 border-b border-gray-200 last:border-b-0">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <span class="text-2xl">${activityIcons[activity.type] || '📋'}</span>
                    </div>
                    <div class="ml-3 flex-1">
                        <p class="text-sm text-gray-900">${activity.description}</p>
                        <div class="mt-1 flex items-center text-xs text-gray-500">
                            <span>${timeAgo}</span>
                            ${activity.user_name ? `<span class="mx-2">•</span><span>by ${activity.user_name}</span>` : ''}
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Get time ago string
    getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = Math.floor((now - date) / 1000);

        if (diffInSeconds < 60) {
            return 'Just now';
        } else if (diffInSeconds < 3600) {
            const minutes = Math.floor(diffInSeconds / 60);
            return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        } else if (diffInSeconds < 86400) {
            const hours = Math.floor(diffInSeconds / 3600);
            return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        } else {
            const days = Math.floor(diffInSeconds / 86400);
            return `${days} day${days > 1 ? 's' : ''} ago`;
        }
    }

    // Create empty state HTML
    createEmptyState(title, message) {
        return `
            <div class="px-6 py-8 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">${title}</h3>
                <p class="mt-1 text-sm text-gray-500">${message}</p>
            </div>
        `;
    }

    // Setup auto-refresh for real-time updates
    setupAutoRefresh(intervalMinutes = 5) {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        this.refreshInterval = setInterval(() => {
            this.loadAnalyticsData();
        }, intervalMinutes * 60 * 1000);
    }

    // Export data functionality
    async exportData(type = 'all') {
        try {
            const response = await requestWithAuth(`/admin/export.php?type=${type}`, 'GET');
            
            // Create and trigger download
            const blob = new Blob([response.data], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `usiu-events-${type}-${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            
            this.showSuccessMessage(`${type} data exported successfully`);
        } catch (error) {
            this.showErrorMessage('Failed to export data: ' + error.message);
        }
    }

    // Generate reports
    async generateReport(reportType, dateRange = {}) {
        try {
            const params = new URLSearchParams({
                type: reportType,
                ...dateRange
            });

            const response = await requestWithAuth(`/admin/reports.php?${params.toString()}`, 'GET');
            
            // Open report in new window
            const reportWindow = window.open('', '_blank');
            reportWindow.document.write(response.data);
            reportWindow.document.close();
            
        } catch (error) {
            this.showErrorMessage('Failed to generate report: ' + error.message);
        }
    }

    // Utility functions
    showErrorMessage(message) {
        const errorElement = document.getElementById('error-message');
        const errorText = document.getElementById('error-text');
        
        if (errorElement && errorText) {
            errorText.textContent = message;
            errorElement.classList.remove('hidden');
            
            setTimeout(() => {
                errorElement.classList.add('hidden');
            }, 5000);
        }
    }

    showSuccessMessage(message) {
        const successElement = document.getElementById('success-message');
        const successText = document.getElementById('success-text');
        
        if (successElement && successText) {
            successText.textContent = message;
            successElement.classList.remove('hidden');
            
            setTimeout(() => {
                successElement.classList.add('hidden');
            }, 5000);
        }
    }

    // Cleanup function
    destroy() {
        // Clear refresh interval
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }

        // Destroy all charts
        Object.values(this.charts).forEach(chart => {
            if (chart && typeof chart.destroy === 'function') {
                chart.destroy();
            }
        });

        this.charts = {};
    }
}

// Initialize admin stats when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if we're on an admin page with analytics
    if (document.getElementById('events-chart') || document.getElementById('users-chart')) {
        window.adminStats = new AdminStats();
        window.adminStats.initialize();
    }
});

// Export for use in other modules
export default AdminStats;
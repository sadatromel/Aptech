/**
 * Dashboard JavaScript
 * Handles all interactive elements of the dashboard
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Initialize charts
    initCharts();
    
    // Initialize calendar
    initCalendar();
    
    // Setup event listeners
    setupEventListeners();
    
    // Load initial data
    loadDashboardData();
});

/**
 * Initialize dashboard charts
 */
function initCharts() {
    // Charts are initialized inline in dashboard.php
    // This function is kept for compatibility but doesn't create charts
}

/**
 * Initialize calendar
 */
function initCalendar() {
    const calendarEl = document.getElementById('calendar');
    if (calendarEl) {
        const calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: [
                // Sample events - replace with actual data
                {
                    title: 'Vehicle Service',
                    start: new Date(),
                    end: new Date(new Date().setHours(new Date().getHours() + 2)),
                    backgroundColor: '#4361ee',
                    borderColor: '#4361ee'
                },
                {
                    title: 'Team Meeting',
                    start: new Date(new Date().setDate(new Date().getDate() + 1)),
                    backgroundColor: '#4bb543',
                    borderColor: '#4bb543'
                }
            ],
            eventClick: function(info) {
                // Handle event click
                showEventDetails(info.event);
                info.jsEvent.preventDefault();
            }
        });
        calendar.render();
    }
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Toggle sidebar on mobile
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.body.classList.toggle('sidebar-collapsed');
        });
    }

    // Theme toggle is handled in dashboard.php to avoid conflicts

    // Handle auto-refresh options
    const refreshOptions = {
        'refreshNow': 0,
        'autoRefresh15': 15,
        'autoRefresh30': 30,
        'autoRefresh60': 60
    };

    Object.entries(refreshOptions).forEach(([id, seconds]) => {
        const element = document.getElementById(id);
        if (element) {
            element.addEventListener('click', (e) => {
                e.preventDefault();
                setupAutoRefresh(seconds);
                if (seconds > 0) {
                    showToast(`Auto-refresh set to ${seconds} seconds`);
                } else {
                    showToast('Auto-refresh disabled');
                }
            });
        }
    });
}

/**
 * Load dashboard data via AJAX
 */
function loadDashboardData() {
    // Show loading state
    const dashboard = document.querySelector('.main-content');
    if (dashboard) {
        dashboard.style.opacity = '0.6';
    }

    // Load stats
    fetch('api/dashboard_api.php?type=stats')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            updateStats(data);
        })
        .catch(error => {
            console.error('Error loading stats:', error);
            showToast('Error loading dashboard stats', 'error');
        })
        .finally(() => {
            if (dashboard) {
                dashboard.style.opacity = '1';
            }
        });

    // Load activities
    fetch('api/dashboard_api.php?type=activities')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            updateActivities(data);
        })
        .catch(error => {
            console.error('Error loading activities:', error);
        });

    // Load chart data
    fetch('api/dashboard_api.php?type=chart_data')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            updateChart(data);
        })
        .catch(error => {
            console.error('Error loading chart data:', error);
        });

    // Load maintenance schedule
    fetch('api/dashboard_api.php?type=maintenance_schedule')
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            updateCalendar(data);
        })
        .catch(error => {
            console.error('Error loading maintenance schedule:', error);
        });
}

/**
 * Update dashboard with new data
 * @param {Object} data - Dashboard stats data
 */
function updateStats(data) {
    // Update vehicle count
    const vehiclesElement = document.querySelector('.card.vehicles .number');
    if (vehiclesElement) {
        vehiclesElement.textContent = data.vehicles || 0;
    }

    // Update maintenance count
    const maintenanceElement = document.querySelector('.card.maintenance .number');
    if (maintenanceElement) {
        maintenanceElement.textContent = data.maintenance || 0;
    }

    // Update parts count
    const partsElement = document.querySelector('.card.parts .number');
    if (partsElement) {
        partsElement.textContent = data.parts || 0;
    }

    // Update revenue
    const revenueElement = document.querySelector('.card.revenue .number');
    if (revenueElement) {
        revenueElement.textContent = '$' + parseFloat(data.monthly_revenue || 0).toLocaleString();
    }

    // Update visitors
    const visitorsElement = document.querySelector('.card.visitors .number');
    if (visitorsElement) {
        visitorsElement.textContent = data.visitors_today || 0;
    }

    // Update current visitors in building
    const currentVisitorsElement = document.querySelector('.card.visitors .trend');
    if (currentVisitorsElement) {
        currentVisitorsElement.innerHTML = `<i class="fas fa-users"></i> ${data.visitors_current || 0} currently in building`;
    }
}

/**
 * Update activities list
 * @param {Array} activities - Activities data
 */
function updateActivities(activities) {
    const activitiesContainer = document.querySelector('.activities');
    if (!activitiesContainer || !activities || activities.length === 0) {
        return;
    }

    // Find the activities list area
    const activitiesList = activitiesContainer.querySelector('.activity-item')?.parentNode;
    if (!activitiesList) return;

    // Clear existing activities except the first one (template)
    const existingActivities = activitiesList.querySelectorAll('.activity-item');
    existingActivities.forEach((item, index) => {
        if (index > 0) item.remove();
    });

    // Add new activities
    activities.forEach((activity, index) => {
        if (index === 0 && existingActivities.length > 0) {
            // Update the first existing item
            const firstItem = existingActivities[0];
            const detailsElement = firstItem.querySelector('.activity-details p');
            const timeElement = firstItem.querySelector('.activity-time');
            
            if (detailsElement) {
                detailsElement.innerHTML = `<span class="user">${activity.user}</span> performed ${activity.action} action on ${activity.details}`;
            }
            if (timeElement) {
                const timeAgo = getTimeAgo(activity.timestamp);
                timeElement.innerHTML = `<i class="far fa-clock"></i> ${timeAgo}`;
            }
        } else {
            // Create new activity item
            const activityItem = document.createElement('div');
            activityItem.className = 'activity-item';
            
            const icon = getActivityIcon(activity.action);
            const timeAgo = getTimeAgo(activity.timestamp);
            
            activityItem.innerHTML = `
                <div class="activity-icon">
                    <i class="fas fa-${icon}"></i>
                </div>
                <div class="activity-details">
                    <p><span class="user">${activity.user}</span> performed ${activity.action} action on ${activity.details}</p>
                    <div class="activity-time">
                        <i class="far fa-clock"></i> ${timeAgo}
                    </div>
                </div>
            `;
            
            activitiesList.appendChild(activityItem);
        }
    });
}

/**
 * Update chart with new data
 * @param {Object} data - Chart data
 */
function updateChart(data) {
    const chart = Chart.getChart('utilizationChart');
    if (!chart || !data.labels || !data.datasets) {
        return;
    }

    chart.data.labels = data.labels;
    chart.data.datasets = data.datasets;
    chart.update();
}

/**
 * Update calendar with maintenance events
 * @param {Array} events - Maintenance events
 */
function updateCalendar(events) {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl || !events) {
        return;
    }

    const calendar = FullCalendar.Calendar.getCalendarById(calendarEl);
    if (!calendar) return;

    // Remove existing events
    calendar.removeAllEvents();

    // Add new events
    events.forEach(event => {
        calendar.addEvent(event);
    });
}

/**
 * Get time ago string from timestamp
 * @param {string} timestamp - ISO timestamp
 * @returns {string} Time ago string
 */
function getTimeAgo(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    const minutes = Math.floor(diff / 60000);
    const hours = Math.floor(diff / 3600000);
    const days = Math.floor(diff / 86400000);
    
    if (days > 7) {
        return date.toLocaleDateString();
    } else if (days > 0) {
        return days + ' days ago';
    } else if (hours > 0) {
        return hours + ' hours ago';
    } else {
        return minutes <= 1 ? 'Just now' : minutes + ' minutes ago';
    }
}

/**
 * Get activity icon based on action type
 * @param {string} action - Action type
 * @returns {string} Icon name
 */
function getActivityIcon(action) {
    const actionLower = action.toLowerCase();
    const icons = {
        'added': 'plus-circle',
        'updated': 'edit',
        'deleted': 'trash-alt',
        'completed': 'check-circle',
        'scheduled': 'calendar-alt',
        'created': 'plus-circle',
        'modified': 'edit',
        'removed': 'trash-alt'
    };
    
    return icons[actionLower] || 'info-circle';
}

/**
 * Show event details in a modal
 * @param {Object} event - Calendar event
 */
function showEventDetails(event) {
    // In a real app, this would open a modal with event details
    showToast(`Viewing event: ${event.title}`);
}

/**
 * Show toast notification
 * @param {string} message - Message to display
 * @param {string} type - Notification type (success, error, warning, info)
 */
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast show align-items-center text-white bg-${type} border-0`;
    toast.role = 'alert';
    toast.ariaLive = 'assertive';
    toast.ariaAtomic = 'true';
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    const container = document.getElementById('toastContainer') || createToastContainer();
    container.appendChild(toast);
    
    // Auto-remove after delay
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 150);
    }, 5000);
}

/**
 * Create toast container if it doesn't exist
 * @returns {HTMLElement} The toast container
 */
function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '1100';
    document.body.appendChild(container);
    return container;
}

/**
 * Set up auto-refresh
 * @param {number} seconds - Refresh interval in seconds (0 to disable)
 */
function setupAutoRefresh(seconds) {
    // Clear existing interval if any
    if (window.refreshInterval) {
        clearInterval(window.refreshInterval);
        window.refreshInterval = null;
    }
    
    // Set up new interval if seconds > 0
    if (seconds > 0) {
        window.refreshInterval = setInterval(() => {
            loadDashboardData();
        }, seconds * 1000);
    }
}

// Export functions for use in other modules
window.Dashboard = {
    showToast,
    loadDashboardData,
    setupAutoRefresh
};

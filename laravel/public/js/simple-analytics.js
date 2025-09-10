/* 
 * Simple Analytics Tracking Script
 * Alternative to Google Analytics - lightweight and fast
 */

(function() {
    'use strict';
    
    // Configuration - replace with your settings
    const ANALYTICS_CONFIG = {
        apiUrl: '{{ config("app.url") }}/analytics/track',
        orgId: window.ANALYTICS_ORG_ID || null, // Set this in your HTML: <script>window.ANALYTICS_ORG_ID = 3;</script>
        enabled: true,
        trackPageViews: true,
        trackClicks: true,
        trackScrolling: true,
        debug: false
    };

    if (!ANALYTICS_CONFIG.orgId) {
        console.warn('Analytics: Organization ID not set');
        return;
    }

    class SimpleAnalytics {
        constructor() {
            this.visitorId = this.getOrCreateVisitorId();
            this.sessionId = this.getOrCreateSessionId();
            this.startTime = Date.now();
            this.lastActivity = Date.now();
            this.pageLoadTime = Date.now();
            this.scrollDepth = 0;
            this.init();
        }

        init() {
            if (ANALYTICS_CONFIG.trackPageViews) {
                this.trackPageView();
            }
            
            if (ANALYTICS_CONFIG.trackClicks) {
                this.setupClickTracking();
            }
            
            if (ANALYTICS_CONFIG.trackScrolling) {
                this.setupScrollTracking();
            }

            // Track page unload for time on page
            window.addEventListener('beforeunload', () => {
                this.trackTimeOnPage();
            });

            // Track visibility changes
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    this.trackTimeOnPage();
                } else {
                    this.pageLoadTime = Date.now();
                }
            });
        }

        getOrCreateVisitorId() {
            let visitorId = localStorage.getItem('analytics_visitor_id');
            if (!visitorId) {
                visitorId = 'visitor_' + Math.random().toString(36).substr(2, 16) + '_' + Date.now();
                localStorage.setItem('analytics_visitor_id', visitorId);
            }
            return visitorId;
        }

        getOrCreateSessionId() {
            let sessionId = sessionStorage.getItem('analytics_session_id');
            if (!sessionId) {
                sessionId = 'session_' + Math.random().toString(36).substr(2, 16) + '_' + Date.now();
                sessionStorage.setItem('analytics_session_id', sessionId);
            }
            return sessionId;
        }

        async detectLocation() {
            try {
                const response = await fetch('https://ipapi.co/json/', { timeout: 3000 });
                const data = await response.json();
                return {
                    country: data.country_name,
                    region: data.region,
                    city: data.city
                };
            } catch (e) {
                return {};
            }
        }

        trackPageView() {
            this.detectLocation().then(location => {
                this.track('page_view', {
                    page_url: window.location.href,
                    page_title: document.title,
                    referrer: document.referrer,
                    ...location
                });
            });
        }

        trackTimeOnPage() {
            const timeOnPage = Math.round((Date.now() - this.pageLoadTime) / 1000);
            if (timeOnPage > 5) { // Only track if more than 5 seconds
                this.track('time_on_page', {
                    page_url: window.location.href,
                    time_on_page: timeOnPage,
                    scroll_depth: this.scrollDepth
                });
            }
        }

        setupClickTracking() {
            document.addEventListener('click', (e) => {
                const element = e.target;
                const tagName = element.tagName.toLowerCase();
                
                // Track important clicks
                if (tagName === 'a' || tagName === 'button' || element.onclick) {
                    this.track('click', {
                        element_type: tagName,
                        element_text: element.textContent.slice(0, 100),
                        element_id: element.id,
                        element_class: element.className,
                        page_url: window.location.href
                    });
                }
            });
        }

        setupScrollTracking() {
            let maxScroll = 0;
            window.addEventListener('scroll', () => {
                const scrollPercent = Math.round((window.scrollY / (document.body.scrollHeight - window.innerHeight)) * 100);
                if (scrollPercent > maxScroll) {
                    maxScroll = scrollPercent;
                    this.scrollDepth = maxScroll;
                }
            });
        }

        async track(eventType, data = {}) {
            if (!ANALYTICS_CONFIG.enabled) return;

            const payload = {
                org_id: ANALYTICS_CONFIG.orgId,
                visitor_id: this.visitorId,
                session_id: this.sessionId,
                event_type: eventType,
                event_data: data,
                ...data
            };

            if (ANALYTICS_CONFIG.debug) {
                console.log('Analytics Track:', payload);
            }

            try {
                // Use sendBeacon if available for better reliability
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(ANALYTICS_CONFIG.apiUrl, JSON.stringify(payload));
                } else {
                    fetch(ANALYTICS_CONFIG.apiUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(payload),
                        keepalive: true
                    });
                }
            } catch (e) {
                if (ANALYTICS_CONFIG.debug) {
                    console.error('Analytics tracking failed:', e);
                }
            }
        }
    }

    // Initialize analytics when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.SimpleAnalytics = new SimpleAnalytics();
        });
    } else {
        window.SimpleAnalytics = new SimpleAnalytics();
    }

})();

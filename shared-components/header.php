@media (max-width: 991px) {
    .site-header-inner { padding: 8px 16px; }
    .event-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .stat-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .hero { grid-template-columns: 1fr; gap: 24px; }
    .filter-layout { grid-template-columns: 1fr; }
    .filter-toggle { display: inline-flex; }
    .filter-panel[data-collapsible="true"] { display: none; }
    .filter-panel[data-collapsible="true"].open { display: block; }
    .event-detail-layout { grid-template-columns: 1fr; }
}

@media (max-width: 767px) {
    .site-header { position: static; }
    .site-header-inner { align-items: center; }
    .brand img { width: 54px; height: 54px; }
    .nav-toggle { display: inline-flex; margin-left: auto; }
    .site-nav { display: flex; justify-content: flex-end; }
    .nav-links { display: none; position: absolute; top: 72px; left: 12px; right: 12px; background: #fff; border: 1px solid var(--line); border-radius: 10px; padding: 12px; box-shadow: var(--shadow); flex-direction: column; align-items: stretch; gap: 4px; }
    .nav-links.open { display: flex; }
    .nav-links a, .nav-muted { padding: 10px; }
    .nav-spacer { display: none; }
    .page-shell { padding: 24px 16px 44px; }
    .hero { padding-top: 12px; }
    .hero-media { min-height: 210px; }
    .search-bar { flex-direction: column; }
    .search-bar .btn { width: 100%; }
    .search-suggestions { top: 100px; width: 100%; }
    .suggestion-item { grid-template-columns: 88px 1fr 16px; }
    .event-grid { grid-template-columns: 1fr; }
    .event-card-media { height: 190px; }
    .section-heading { align-items: flex-start; flex-direction: column; }
    .form-grid { grid-template-columns: 1fr; }
    .field.full { grid-column: auto; }
    .split-card { grid-template-columns: 1fr; }
    .dashboard-layout { grid-template-columns: 1fr; }
    .dashboard-sidebar { position: static; display: flex; overflow-x: auto; gap: 4px; padding: 8px; }
    .sidebar-role { display: none; }
    .sidebar-link { white-space: nowrap; }
    .stat-grid { grid-template-columns: 1fr; }
    .event-list-row { grid-template-columns: 1fr; }
    .event-list-row img { width: 100%; height: 190px; }
    .event-list-row .row-action { width: 100%; }
    .ticket-option { grid-template-columns: auto 1fr; }
    .ticket-option .ticket-price { grid-column: 2; }
    .form-actions { flex-direction: column; align-items: stretch; }
    .form-actions .btn { width: 100%; }
    .footer-grid { grid-template-columns: 1fr; }
    .footer-contact { text-align: left; }
    .footer-links { flex-direction: column; }
    table { min-width: 640px; }
    .result-card { margin-top: 10px; padding: 28px 18px; }
}

@media (max-width: 420px) {
    .event-card-media { height: 165px; }
    .page-header h1, .hero-copy h1 { font-size: 1.85rem; }
    .content-card, .info-panel { padding: 16px; }
}

/* Keep existing filters visible and account navigation reachable on smaller screens. */
@media(max-width:991px) {
    .search-layout { grid-template-columns:220px minmax(0,1fr); gap:16px; }
    .search-layout .event-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .dashboard-layout { grid-template-columns:180px minmax(0,1fr); gap:16px; }
}
@media(max-width:767px) {
    .search-layout,.event-detail-card,.dashboard-layout { grid-template-columns:1fr; }
    .search-layout .event-grid,.ticket-grid,.post-grid { grid-template-columns:1fr; }
    .dashboard-sidebar { flex-wrap:wrap; overflow:visible; }
    .sidebar-link { white-space:normal; flex:1 1 140px; }
    .search-form__row { flex-direction:column; }
    .hero-event img { height:180px; }
    .ticket-tier { align-items:flex-start; }
    dl > div { grid-template-columns:1fr; gap:4px; }
    .result-card { max-width:100%; }
    .nav-links { z-index:100; max-height:80vh; overflow:auto; top:76px; }
    .site-nav { position:static; }
    .event-card__image { max-height:240px; }
    .content-card,.panel,.event-detail-card { padding:16px; }
    .button-row,.button-group { justify-content:flex-start; }
}

@media(max-width:767px) {
    .booking-layout,.registration-layout { grid-template-columns:1fr; gap:20px; }
    .events-page .event-card { grid-template-columns:1fr; }
    .events-page .event-card__image { width:100%; height:auto; }
    .event-detail-sidebar .ticket-options,.summary-card { padding:16px; }
}

@media(max-width:767px) {
    .organiser-profile-page,.policy-layout,.privacy-grid { grid-template-columns:1fr; }
    .organiser-profile-page .event-grid { grid-template-columns:1fr; }
    .policy-contents { position:static; }
}

@media(max-width:767px) { .support-layout { grid-template-columns:1fr; } }


@media(max-width:767px) {
    .organiser-brand, .post-author { align-items:flex-start; }
    .organisation-avatar { width:60px; height:60px; flex-basis:60px; border-radius:16px; }
    .reaction-controls--compact { gap:8px; }
}

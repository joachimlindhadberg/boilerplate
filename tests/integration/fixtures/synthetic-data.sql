-- ONLY for the disposable integration database. Synthetic data, no users/password hashes.
INSERT INTO roles (id, role_name) VALUES (1, 'admin'), (2, 'editor'), (3, 'user');
INSERT INTO settings (setting_key, setting_value, autoload) VALUES
('site_name', 'Boilerplate Integration Test', 1),
('site_tagline', 'Syntetisk testmiljoe', 1),
('site_url', 'https://boilerplate.test', 1),
('admin_language', 'da', 1),
('timezone', 'Europe/Copenhagen', 1),
('language', 'da_DK', 1),
('maintenance_mode', '0', 1),
('theme', 'default', 1),
('seo_indexing', '0', 1),
('navigation_header_menu', 'primary', 1),
('navigation_footer_menu', 'footer', 1);
INSERT INTO navigation_menus (id, name, slug) VALUES
(1, 'Test hovedmenu', 'primary'), (2, 'Test footer', 'footer');
INSERT INTO pages (id, page_title, page_slug, page_description, page_content, content_mode, page_status, menu_order, show_in_menu)
VALUES (1, 'Integrationstest for Boilerplate CMS', 'forside', 'Syntetisk testside',
'<section><div class="container"><h1>Integrationstest for Boilerplate CMS</h1><p>Syntetisk testindhold uden persondata.</p></div></section>',
'legacy', 'published', 0, 1);
INSERT INTO navigation_items (id, menu_id, type, page_id, label, target, is_visible, sort_order)
VALUES (1, 1, 'page', 1, 'Forside', '_self', 1, 0);

INSERT INTO cms_integration_marker (id, token) VALUES (1, 'boilerplate-z8-synthetic-only-v1');

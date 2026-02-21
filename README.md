# ailaz
# ailaz


### 3. Vollständige Dateiübersicht — was muss existieren

Hier siehst du auf einen Blick was du brauchst und was noch fehlt:
```
src/
├── main.js                               ✅ aus Schritt 4b
├── App.vue                               ✅ aus Schritt 4a
├── api/
│   └── client.js                         ❌ anlegen (Code aus Schritt 4a)
├── router/
│   └── index.js                          ✅ aus Schritt 4a
├── stores/
│   ├── auth.js                           ✅ aus Schritt 4a
│   └── artifacts.js                      ✅ aus Schritt 4a
├── views/
│   ├── public/
│   │   ├── HomeView.vue                  ✅ aus Schritt 4a
│   │   ├── ArtifactView.vue              ✅ aus Schritt 4a
│   │   ├── TimelineView.vue              ❌ Platzhalter oben
│   │   └── SearchView.vue                ❌ Platzhalter oben
│   └── admin/
│       ├── LoginView.vue                 ✅ aus Schritt 4b
│       ├── DashboardView.vue             ✅ aus Schritt 4b
│       └── ArtifactEditView.vue          ✅ aus Schritt 4b
├── components/
│   ├── layout/
│   │   ├── AppHeader.vue                 ✅ aus Schritt 4a
│   │   ├── AppFooter.vue                 ❌ Platzhalter anlegen
│   │   └── AdminSidebar.vue              ✅ aus Schritt 4b
│   ├── artifacts/
│   │   ├── ArtifactCard.vue              ✅ aus Schritt 4a
│   │   ├── ArtifactGrid.vue              ✅ aus Schritt 4a
│   │   └── ArtifactFilter.vue            ✅ aus Schritt 4a
│   └── ui/
│       ├── LoadingSpinner.vue            ✅ aus Schritt 4a
│       └── ErrorMessage.vue              ✅ aus Schritt 4a
└── assets/
    └── main.css                          ✅ aus Tailwind-Schritt
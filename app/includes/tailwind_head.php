<script src="https://cdn.tailwindcss.com"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: {
                        green: '#9acd32',
                        greendark: '#7fae22',
                        dark: '#2f4f4f',
                    },
                },
                fontFamily: {
                    sans: ['Inter', 'system-ui', 'sans-serif'],
                },
            },
        },
    };
</script>
<style>
    /* Brand gradient used site-wide (login page + main app body).
       Fixed attachment keeps it stable while long pages scroll. */
    .app-bg {
        background: linear-gradient(135deg, #2f4f4f 0%, #446055 40%, #86bc42 100%);
        background-attachment: fixed;
        min-height: 100vh;
    }
    body { font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', sans-serif; }
</style>

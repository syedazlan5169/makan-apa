import { defineConfig, loadEnv } from "vite";
import laravel from "laravel-vite-plugin";
import { bunny } from "laravel-vite-plugin/fonts";
import tailwindcss from "@tailwindcss/vite";

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), "");
    const usePolling = env.VITE_USE_POLLING === "true";
    return {
        server: {
            host: "0.0.0.0",
            port: 5173,
            origin: "http://localhost:5173",

            cors: {
                origin: "http://localhost:8085",
            },

            hmr: {
                host: "localhost",
            },

            watch: {
                usePolling,
                ...(usePolling ? { interval: 500 } : {}),
                ignored: ["**/storage/framework/views/**"],
            },
        },

        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.js"],
                refresh: true,
                fonts: [
                    bunny("Instrument Sans", {
                        weights: [400, 500, 600],
                    }),
                ],
            }),
            tailwindcss(),
        ],
    };
});

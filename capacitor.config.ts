import { CapacitorConfig } from '@capacitor/cli';

const config: CapacitorConfig = {
  appId: 'com.hydrofarm.app',
  appName: 'Hydro Farm',
  webDir: 'public',
  server: {
    url: 'https://hydroponic.ahmadhasan.my.id',
    cleartext: false,
    androidScheme: 'https',
  },
  android: {
    allowMixedContent: false,
  },
};

export default config;

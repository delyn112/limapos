const { contextBridge, ipcRenderer } = require("electron");

contextBridge.exposeInMainWorld("electronAPI", {
    printOrder: (orderData) => ipcRenderer.send("print-order", orderData),
      getPrinters: () => ipcRenderer.invoke('get-printers'),
       getDeviceId: ()  => ipcRenderer.invoke('get-device-id')
});



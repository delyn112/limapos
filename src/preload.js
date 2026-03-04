const { contextBridge, ipcRenderer } = require("electron");

contextBridge.exposeInMainWorld("electronAPI", {
    printOrder: (orderData) => ipcRenderer.send("print-order", orderData)
});
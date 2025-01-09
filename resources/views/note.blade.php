<pre>PiOS 4b 32-bit OS Lite
sudo apt update && sudo apt upgrade -y

sudo apt install lxde-core lxde-common lxterminal -y

sudo apt install watchdog && sudo systemctl enable watchdog && sudo systemctl start watchdog

sudo raspi-config


crontab
5X 5 * * * sudo reboot
50 Fullmap1
51 Fullmap2
52 Fullmap3
53 Yardmaplarge
54 Yardmapsmall
55 Rowmapfirst
56 Rowmapmid
57 Rowmaplast

echo -e "CONF_SWAPFILE=/home/chood/swapfile\nCONF_SWAPSIZE=1024" | sudo tee -a /etc/dphys-swapfile > /dev/null && sudo systemctl restart dphys-swapfile

echo -e "[Unit]\nDescription=Relaunch Browser\nAfter=multi-user.target\nWants=graphical.target\n\n[Service]\nEnvironment=\"XAUTHORITY=/home/chood/.Xauthority\"\nEnvironment=\"DISPLAY=:0\"\nExecStart=/bin/bash -c \"midori -e Fullscreen --display=:0 https://www.cbwcw.co/$(hostname)\"\nUser=chood\nGroup=chood\nRestart=always\nRuntimeMaxSec=3595\n[Install]\nWantedBy=multi-user.target" | sudo tee /etc/systemd/system/relaunch_browser.service > /dev/null

chromium-browser --kiosk --disable-infobars --noerrdialogs --hide-scrollbars --disable-translate --no-first-run --disable-features=TranslateUI --start-maximized https://cbwcw.co/$(hostname)


sudo systemctl daemon-reload && sudo systemctl enable relaunch_browser.service && touch /home/chood/.Xauthority

sudo reboot

</pre>

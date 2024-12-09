<pre>PiOS 4b 32-bit
Refresh Rate!

sudo apt update && sudo apt install cec-utils && sudo apt install watchdog && sudo systemctl enable watchdog && sudo systemctl start watchdog && sudo apt install midori

crontab
0 6 * * * echo 'on 0' | cec-client -s -d 1 && sleep 5 && echo 'as' | cec-client -s -d 1
30 19 * * * echo 'standby 0' | cec-client -s -d 1

echo -e "CONF_SWAPFILE=/home/chood/swapfile\nCONF_SWAPSIZE=1024" | sudo tee -a /etc/dphys-swapfile > /dev/null && sudo systemctl restart dphys-swapfile

echo -e "[Unit]\nDescription=Relaunch Browser\nAfter=multi-user.target\nWants=graphical.target\n\n[Service]\nEnvironment=\"XAUTHORITY=/home/chood/.Xauthority\"\nEnvironment=\"DISPLAY=:0\"\nExecStart=/bin/bash -c \"midori -e Fullscreen --display=:0 https://www.cbwcw.co/$(hostname)\"\nUser=chood\nGroup=chood\nRestart=always\nRuntimeMaxSec=3595\n[Install]\nWantedBy=multi-user.target" | sudo tee /etc/systemd/system/relaunch_browser.service > /dev/null

sudo systemctl daemon-reload && sudo systemctl enable relaunch_browser.service && touch /home/chood/.Xauthority && sudo systemctl start relaunch_browser.service


/boot/firmware/config.txt
[all]
hdmi_group=1
hdmi_mode=33

sudo reboot
</pre>

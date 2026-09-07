<?php

declare(strict_types=1);

const CRM_PRODUCTION_OPERATIONS_V2 = 'CRM_PRODUCTION_OPERATIONS_V2';

echo "CRM PRODUCTION OPERATIONS V2\n";
echo "=============================\n\n";

$root = realpath(__DIR__.DIRECTORY_SEPARATOR.'..');

if ($root === false || ! is_file($root.DIRECTORY_SEPARATOR.'artisan')) {
    fwrite(STDERR, "PATCH GAGAL: Simpan file ini di folder tools dan jalankan dari root Laravel.\n");
    exit(1);
}

if (! function_exists('gzdecode')) {
    fwrite(STDERR, "PATCH GAGAL: ekstensi PHP zlib/gzdecode belum aktif.\n");
    exit(1);
}

$encodedPayload = [
    'tools/ENV-production-operations-v2.example' =>
        'H4sIAAAAAAACA3VUTXObPBC++1doplfsuHG/JjM6yCDH1BgogiTuBcsgGxoMVBKJ3/fXd5GdjBO3ByG0z6Nd7ecHFMom7zJdNjUqc1HrUv+HeJ0jLXmt2kbqAQnDlPp3uH1lGpFDp8kt3vJKCXNOIg8XWrfq5uoqk/uROPB9W4lR1uwNHrtL+jPwKSaq5Fff+SOXmg/saJnOgsim6TyOQ4a17MRg8AH96EQnbpDsarRuixYBuVS8Rr+N/LmRj2g4NAeciy3vKg1nVQnR4gn8aVkKhSfrwY+EJjS1A9+nduwGPs655huujJGgFZL3DvEKFQJMbATXSBdSqKKpcmVex+w5dRKPRimLiUfTpesnMWV4YtCj/rfIZ4PQJXG9lK18+x388YhPib1IwhM2D5KI4esv/aumPHvsWqSyQuRdJUw2bmcMSaH7/ED4++sOaF/BNyZTwuiLtj7IeHx9Mx4b0j2lC2DNEs97YThkhf8JHq9PXq6fpAtKw1d7K3Dg0wV60tVvDH+7gJeBH88BNzsouL5grCiJgNBvDH/tw0C05lmxB5dRs/klMo2UbiTfiRFaQJrRumoyXq1RBzGp0L7cHVNpwiUFz6+eZakFgqrdwJcrNTJGSRwTe76kfpw6Lltgo8UgSegFxEmX5CFdTPH1+NO38bmceF5wT52UPsTUZ1BKDLf51vrV7mCJndXWO+tZbFprV24tfdBWpp6svMn6dbAOlerXwfq/bC2xr47ld6q9zTHjb91Ez6UukBItB8eEhSrBlR62snwqKwFwJoXpV2jA82gG0+9Q6UffoA2HR91DNfkLKYzozH04o73RRO5ZSmybMgYpWqWug9+jjNoRjc9IFwyHzkjixWlEb/vm4/CQptOFceXje/I0sRc0vtDRD5b3Muo7YeD6fyFDL4QknkNfraCvXnnHOQVBtyu+J3cjRGu+geZq6grm3VYLiTJAVAYzplSorJXmVSVyU07rV2g4fBJSQdbWqJ9Bp5qyPbIkd2CMTD3qnGydAVPXJ9EKv2iB0fgG7rsuSOI+nIHvMPxlfI7O+jFiewEDzWY6/gH8DcxStAUAAA==',
    'config/crm-production-operations.php' =>
        'H4sIAAAAAAACA42Wb5OiRhDG3/spfIdWkWSze7lcXbK5GnGMRBQjcLtWamtqhFY5hz83DLuaT58GWVdE11iFo0D/uul5eGZ+/5Ku01ZLgspl3P6n1caPtgYu1Fpr3/9RnSnPZv4aglyAZJniAlgUxrmCrLytE8aq24b4uaMZszFzjCHtexadMcclFmVjc+K51NH09l1XfyN+zyGH67S/PerRBumXYxJEPBQs28X+dRwdE9NiznxiNJg/16AL7m/ytAKuk1yexfWIMfKmFWpoe7MCdPuxAj3prdYR7LSnAZa9YwFXfMEzYCqMoLzlQO9jrXP8dkmPOPQ1mWuOKWbRbm4/39xoxzW/AGyQuMyFOEN7oHSEuIFnWQ3U3XuogO/OPfsZXp/MEXdzDNoApOz1SXfvNXFE6fTwyPNyRj40SFVdxXCVVRVYDAXtUwMWJbFaI60cr+LG9sQdIq8cy/JuG8QdcInAYrjKm1MyQ1wxFLRfj2HJ4hv4igVhtqlPYgWwe39Rw2V90xlpZ+JSCctwW0YqGUadToZDvOpe4kxndGA+FkrwZfTDXq0ZgtvaT+fwmUoknKh5r2gZPoMsr2jZnabXr25gd/ZhyIPDiGFQx8HGzJnZP865dx/w0aQuBjvUmFH3iNEASFiFSXwR0KcD4lkum9E/TXvSiF7k/uad9D3PGFG3EZVLcTHEm1mN+yEO0gTlcjGITvpT25ycyYTukXK1xonZoVvVQJ1Fkoju+SLQU6bEHaJ9zdG+Dni9veQig9Msai2Tl0pTObxdfDoxO64U99cRxCprOF5Dz8R1iTEc00klZpScSHwuarKL+JZtQpEsdhdc3ZtaNumzMXlko17hwDcfPtVciAuRvEDAYKsgzlAKewyXku/YMxc5ZJ39n2UoFMjqT8TTzkkT8HU60TVsU5EE0NF0zFx702q3leFHxRLLsh9on9FHl04clJ1zwi0D0mCpf0tXeMBKT+OV/gKLVF+FS11tle5nz3qQ+MWx1bciK46t/m+Y6hAJrUbrdltvP0/XJx6r8DmUeWPCIOYLAUF5et8Z7JbsHBpvWGRMvqJ0SM+i/TfltAem5eIO4CuxTFy/cO2ybau2vIYxlyd2UMF65oTMinVkOkSDctiAjHFNaN/f37e1hzAOkhcs8wtaleBR5vP4R9iiGX1+O1HTTrEOJjl6FvhJHJxVT5W3WAxtzy3cxJ70C0v+WNPQsthl+CLJrvdjUGwyDMt2yp4Ur8v7LTlMRCqTIPfVq1cdzcQykT5uRJRKs4vZB/bMwG2I606d/zcVmPfpt9Z/9slkOAcKAAA=',
    '__dashboard_fragment__' =>
        'H4sIAAAAAAACA51WW2/bNhR+z684C4qpBsLFcZpuy5w0RTtgfVjTpdleisCgrKNLTFEEL7MNw/+9R5TkSPEl2vggkJT4fefy8RwdQT1WK8bgw92fky93tx///nD/6fbz5PbL73fvy9nXyT8jYGy9Pmo+v8lieP0DYK7s8vWrQqHmNiuk+QO5sOlgsPmwHOMo+xemghtzdZxb9gZ04WSEEVsICAsdoYYwYfM0swiKXYBJeVTMmckh4np2WX3CEs2X7JfhsN5Mqo1fh8Pj6w7bFmNIjLHAhX+wueYKiCk3zFiuLTw6Y7N4yUK0c0QJCVfsfAdmg7v7jX+rGk6LC8sWBuJCWmYwz8JCROAUxWnKDYLVfDrLJDmdRbQqvy40lwmyt6U/X5eGDITbTVjHp+oAbTrq8oqK13P6nW7o/JaPNjFNU4ycoAz8CH85dAhVBsen6ai3o5SoJ5oL74Dl1hngM+u4AKULgwaW5CDkKB95wiGkADh1QgrimTghwyQonKF+5DQrXyZeJD/t9Xx8ujcXY6MIpLaw0VrshIBGNWcUC7Vg56BoDruz9eTS2+ei64bSb53v1GEz3jtbgMZYo0nBcG7puEnDgusIoix0M77Hx9KTHfKufD8o+0RnUaVlMPlluWLTQhg2goVoLd/ssfomLjTyaQrbtxu4gVepn34imQ72en2jUrX3ZTlqlFotV23Ub4Hxu8EDvHsHgZMzWcxl8FsfvPtCIqHl3Hr72yQDWB1EKEdQHVgGcHUNQV19MKcgiIiNKPUkg2Z5Max00qxJGcHJywzaSUnXv8MQCocNvJ832H7RE3jOtQc+eQpZm4PnIT1rkmrRsFSrfjQRxtwJ20bWuIlNOW1Qy/nLmOv9ab1BGZUy2l+LWprf7iuKav9q1RHGen3goj5H9H2j6hZTlJYQd7WL0QuI3ZrZKTPH10/mVboXPERBsl+vD1b9XsXOV/nTn32xG7WK3bezoVo8tNrEpjW17anvZWnI7jrUsx5vh4D+As6h6R3P4nFPN2mWZvoStiNj7MRQ0Cepy7msSwML+oSq+8PSRs3RGJ5g8DAY/IckkgdnIDKJjDZy1bhDXaRQfJrZskls53bD1ctkkn4WH/2PgJcn6/p91OPYs62a9zth9IsRGwoAAA==',
    'database/migrations/2026_09_04_230000_create_crm_operational_heartbeats_table.php' =>
        'H4sIAAAAAAACA51TwW7bMAy9+yt0GGAZWIFipyEeOmDYBuSwU3cMYDAS7aiVZU0k2wxD/n1ynKRFs3RJeSIovscnUe/T57iKRSGEau699C4A4+IrMCyBcPHDdQnYDYGe0vpk961ZYQ+LL14wJhf4qPNWYhwSL76DAYu0A9RFkZAlBRXwURkPRArXjMGSOkwt/hQqR5Sld0a1EsxYVRJ1NVMPg7Pb46lpDNcqPdHPZiugn7D0qEuT+maIOFGCb1YIiZcITGVVPUOPMWmqD7VNcUj3xCZl6Ou075+06sPDqHc86nk5cape3Tirq/qfJ8QZ3unyHn9n5o/X1dWNBPdL8H8AYmAZ1XwYMRZbEM+6lHAfhsdQ5poLFtenaNj1mCn6qMu8Hm5ynhhtAzxCg3i/fd9z0WIMEh2hLxTRgvNv14AYLhUggVwX0M4DY4dJl1amlTc9naPhCG+ysdAIuwfc3kYSbon2+7k+dZlsD13mCxF0eM7kOxrCCGC46K3oecdml+988NKJNv+jE17c28WmIc7bb2tHTK97cT9pUxd/AekTYO2iBAAA',
    'database/migrations/2026_09_04_231000_add_crm_production_query_indexes.php' =>
        'H4sIAAAAAAACA9VYTY/bNhC9+1ewgAHLC29ztxO32G4L5NBe0ls3EGhqvGZXIlWS8nqR7H/vkBQlWhblpMmlOknkm8eZN8Mvvf2pPtSzWaOBvC/LpuKCGni4p4buqIaH3/mjooZLofvXTRL9gR2gog93ZQO14sJcID80dS2VefiNMlqAfri/uwrxnJvZTIFplCACngkrqdYETgZEoUnn2OzTjODz5uaG/HykilCl6MtbbdCVx9XoF/q4Ir5pu92SmzeOAH0/oiseQ+ZcFHACTd6Rv1y3fRb/NNKAXpB326jV9TBV5b43LyRrKhAmL6V8auqcFydv4c1z0VQ7UIuPqyRDreTfwEwOR0vT24d2JgtYrMjC9xfo9RSbfBagcqYAcUXEhilQ+GmJQic1MVH0uuDiKDlLxx76J6JvIVPxdyzfSYGOTxtqGnSugYjMN1oa234e+yWFT56C0lXdRVZRyOmIQGk0G7Fve84JYu2fpXrKpSoQl5I/gkxkoEdNJSHmCjlr9btMpq+fVslrbMNsjmcvDr2Akh9BvVwJfwCbkOAcOSXDkPPbpRgyfqciH9JGWR5NfbrQ6kaxAy7pV9QewCbUruWUwkOeb1d4yIjCFVJdErbtX81XU24XyThC35JSFE41CJ1eNkN/F7vNcjLyFp0uhY6uX9Ajsi9a5TGPEmtJG8me8koewWY26X8HyLmBamST6RkdYGq7GSHFDR/MJKtHfCWtgj0oECxWum8zL7WbdjHqCqE1GXEzADrK6xlwmzIefXiZV6A1fZyoHYeijMkGh1DAAJeBePjQ5dXpAAl5PJ0bfy9Lu1CMUEZnBg+aYI7DsgMIafieM3+wTAV1BsollnPejjniggIty3Zg90kTPnzEk6Q74zW7kjOybwSzA5CmzpZrglOscN2fOru9RDJ2INncHLi+3YbTINVkjsfeEqwv4Yy4jAztw/ck+4H4I+x6jevHn9Yi84bLIdo+TArDRQObs57X2dln71PsjaCVd4ZJPE0LPUbvHWpDoWX5i4f+euLatG6tIoLPn0kctoPpHmdHHI0iHclINPYJEplIn1Wfnay7UpD5Lrwuib06ZMHb4E/Cnd6uDebCcMTR5TAN52+v47VU4Dn7/1hN7r6TP8GLznoXQmWlquk/10ci5clkW2GiHBZK1u99Hv0wY3X2JclrL3td9oazwl8PSXCwvRSGKbImOynLZJZbmFPRv19Nqh98OBlHZWyvxHtaakhX6mwANyoUREqCOJmD8MOn03w0+HnR/g7AC/P93XqNNSjA8WbL2+0jmPC74A+kyJabC/+skS8K3N1Ryspfk7ST6Ed7RkPHONOL5VnIt9vnA+7S2cLZtnDcDDp/JuE2Hgv2c2kc6WTpkE6BARC8Zssg7+tm9i8hR2iqYREAAA==',
    'tools/install_windows_crm_workers_v2.ps1' =>
        'H4sIAAAAAAACA6VUXU/bMBR9z6+4QkhJpDmD7QmkPVTAgI2Prq1UaQxVXnJJvSZ25o8WxPjvuw4JpFC2suUhiX3tc88999gV17yMAqDnwlgtZH652dfqB6a2z+0UPkA0QKOKObJ6HH1SQt7/bvaHw1SLyg6UshAmSRjHiY+8eQI3rRqosJpWCV5jGMRBsHmgtdK91Aol+xqvUKNM0a8aWlWFQSCuIGKSoKMRGrsifYdmyLUVhkuiEMNtnd9OtVrARhMAKzI+g0xYLN2MxplYQtgI7oiSSaeYuQIbWkTmDBds2MxmI25mTYQdXGPqLMJDeaync1eitA9koIXb1U6GwMZKz0iRfaEpqdI3SwQ6yUda5DnqVdnbEDv3WrEeiXOIlu1zi3Hi30kvy06FJGYm2o6BDbBCKzzlY2lRz3kBkUcdiRKHFXFkzWrYjokCWksMzarUwyZG37Z6QvU4J6IU9kXYrZqFsSTKnnIkz/uH8RqUBpgLQ6uWuQDz7zNeIoR7g1M4IRfPsYB2kSa1m0Y96ylrNXwuOGtrhEcl2MDJE/TgRyKfEm9gH5Um8X/BubPszBUFGeenQ4f3p4G0e8mkVqnCfCMzTFJdTuo9kwWZAvVk/i5JyyxskNa3X0i76iPV8V8Uvk1h43brboNmr6BLLv6rC+vF6ziwZ4e+h65qq/8f71y0zb/c3f2KWj31zM7OK0xD/66woirwWNIWOikGjnOpNNKOVzrqiy8NxnWTOqbqdunRUEvadcy0rM86jqql/QeGQTDWdMWxI0Ww4cX550tYeT6ABKg43VF5EtbpMdckdLanCqXhUCPKPyN1s74WrM+NFf4Otr4ssmyeO8n9hDOENhYyUwsDNwRI0VIUYiaAzwy1cYZQ3bsVMn+Jc8u/c4NJGPwGbJinE8kGAAA=',
    'packages/Webkul/Admin/src/Console/Commands/CrmAttachmentMigrateCommand.php' =>
        'H4sIAAAAAAACA6VXbXPaRhD+zq+4ZDyRSDD2Zzk4dTBuPa3dDNhtM+Ayh1jQjU86zd3JNnXIb+/ei0CAiNNGH0B3t3v77Pvq/Yc8yRuNjKagchoD+RMm9wUfnU1Tlo26IlOCA/6nKc2m6qTRKBSQS84LPKYatilOts8HRZ4LqUcXNKZTUKPzjy+SDOIEUvoymRaSzgEhxZwqRboyPdOaxkkKmb5ic4lMHhWBJw0In/h147lB8Mml0BBrmJIDxeYopJBAOiSIZRrR1VXqMHWXkWdN5Rz0kjwfHs6kSDtcxJTbJTxBXGhYBifbNyPUWLJcM5GZu7siXxAJM5CQxXje7V+RiiyiBaGZ0AlIMmMc1EJpSMmUqXvyyHQiCk2mwEGzbE6QiihRyBjaKNcKPnr7lvz0QCWhUtLFe6UlErb8imW6RdzW6Sl5e+ShsgejnCUhB0Ylg1Uh2KElME+AvpBj9Arj4wragHROyTBQzhPjnOokuGutuVAgyIzyMQaXMhT/gTdHvRKqYCzkFGRJntOF4R6jgcVslwmecsgUlNQSYkDTO7oW4klrRT1gbIiViCx/zMcVSuSzW7sIERYayrOxKeJiejGeirhwGCsMd94/eTHhLCazIottRCQYjRzCZkTQVpbieSXgwMTYNSYmuiJ0bmuSA50wdXgqbECFgSEJmidrHheie7jwzEILA0e2welD2LBNhOA7ovy54VkxsRkJKzA7nSqAZkUXh83eB1IKGQbnJqRVkU4w0qc0I44PDSILRXBzAlPargI0jwTM0Ywo4LMouji7/O2231tTLBubtkNVfJGIIpNBa6i7FtulrShSoTZONbnhlVln8idzEFZJY5EzzPAOOa5spkwpk7ubu+qe5XlJ29gyGGcZhOHKPx9I0Pur17296QUkIsF5/zPp314HzXZAgvZKQ1wdnpqNtRpYJCqVpxO0Y1FgLDiVmlW3zoQEzFPizwhVTvFtjxr3v3K2Rr8+MaWVY2luU1Z1f/fuZPfMqfpIJUba8OpyMLi8/vnO4LfX7TLEArMtK2DzpBIBq/B0BtjCR968IasTxf6Bct/GsFOosl2rjndanTrfje7VKu9qRVQCIBh2f//0+Vsm8QH3v/EcYLUAarLGGwBX04HdC+tkWuuWPGi2GeUKfszx/d7ZOTF5/cOu13JRg8SbvHR8Xvjwb5XKt7CSPzDFJoxjLbelPfAdMrirDQMrLJHikWTwSEZ9TCmWQu8pBl83P0FWcKbWJW5O55RH31BwuakYDgLYQ/k+fZgaS3BjQOmOvUBnMRdqTfai6NqArU+oL1/qE+rV9yfURn8Y/tHrX158fjEavt0S6sJ8N02WOzWXZTMRvh7gpKUoi4hj6Tx73iXxuY87/m1JfJDjln9bvt5oM/beGAdQ24AvcLgr259mU3qPM15C80K1ye2EJmYuHJ/d3Jx1f7nqXd+Mzy8Hv5phYUFRUw0cKUp5x7Z9PoBkM3ZPFSM05+6FFxzv22jY3lrrNoTeOcaW4sw3uO12e4MBtpUacy4rE6a/Znes3J4qV4POTqOMHPf2zFP21+FdbT/yBXE1pJrOpOkEbYmZit7Br4VM1bcp91kRRThU3hiO0DHWhuJ3lZg1Ki/XonHvzb3FpwKja0k9jtaKdV/y1oOqAbYB7vxjFOmqvlhs8esCroW+LjgvweN2zov4frW2yjxQXsA+PNZX6CqOrk/NqIl1KOf4DRkGo5EZm4/wx56t51B3YdMeVuNyp6+4uzE6g8CUFnO70Z+yTJUlO2hjbJvDXMJ8nFIdJ2Hw9e9E61x9iI6OvjKUv7/cvGzWPaZdx+nQ/t2hDbSs90v9armTkDYXxvewUOU8VibdsvEvysIhh6MPAAA=',
    'packages/Webkul/Admin/src/Console/Commands/CrmAttachmentStorageAuditCommand.php' =>
        'H4sIAAAAAAACA5VUa2vbMBT97l9xCwXJULvbGGM4a0qWtVthg9GsjLGMIss3sRpZMpLcJrT575MfSVo3HUwfbMvSPefc54fTMi+DQLECbck4wk9MF5WcjrJCqOlYK6sl+ndRMJXZQRBUFuFCysofM4f9G4P++aQqS23c9JxxlqGdTpw2bI4eiEtmLYxNMXKO8bxA5brDUZUJ1wECLh16Zuj2wX0AfpVGO+QOMzi0Yu6ZKoNwAoSbIrEtSsJqGLiPojsjHEbeJMU1GfTtvSpuROmEVjXCOEe+AJcjcK1mYu6BM2BbiVBrShlfVCXo9MZjQMcXe+gWu0ql4DCrFG9Ac28ikYYJCOWaG60P9TrcIX8SduEFUOuMUPOwo6e1S7X2rGrQIl2iYfWXjXe2Ns68NTkCIjVnkoSDHYPLhY2GUiikZBdqqA0SIHFPQdg5US9nVo+kNmBZK7JLVJLUe/oPiHqJGdBOhW7CTMmjjJAw7JE0RCVzeZ2O0ohbX0fHOTLp8ojX2TkmsdJ3NIyGM20K5ij5VWRfhCVhTCISp0K9yXFJjY+7Lq7TlUNL34b+MHZLRwb7yFZSs6wroKhLaKsv2ZI5fWH1+3evXk+aDNFwD1Idj2hYVo42HhxtsY/gN7kVVqRCCrcicDLc+kb+9CO2idrBBhCXwjrbYobw8LD5P0e3+XlwcrIl2xfRJqG50Xeg8A6ml5VyosCzJccuJ5fIsojNHJq2YaBxH+ZszmRM9ji7Dl7yP0OJDjtle6LUFoNQM/2sJMF4GceNgOMWptWRwPfRZNKXsQaUft7cv0TB/dDwyJR8rhRbMAVPhgH4GFQL8Dfm1Y14zvzM657HT1rr45OZsOku+h/t3E6VuEW4bho6hFOPQlP089RjLrqJxKwIfd/0qsagn4IKLMpZkkyuxuOzyWSnfw2cOZ4Dnf6oq4ClEuEQN+nvV0znGhqjDSVdu0M7Uc9HF1+bybG1bgrxG1rrL9GwF7Qnsmrbq8uzR7KC9rkO/gIAuWGCigYAAA==',
    'packages/Webkul/Admin/src/Console/Commands/CrmBackupRetentionCommand.php' =>
        'H4sIAAAAAAACA3WQy07DMBBF9/mKWYCSSBT2KS0qEQsWbBohFrRCjjOhVhzH8gOIUP+dyYOUFnU2ftw7x3d8e6d3OggUq9FqxhFeMK+83KyKWqhN2ijbSKS1rpkq7DwIvEV4lNKTzByeOua9fsTI0HwIjnaTmvqe8crrNTpUTjRqlIjKJbMW/jtGLOAXXRTkGM7BdwBU2jQOucMCLqx4pzzeICwg5KZO8h40M7+kcH7aUqDlRuhO7JpWWssWCiZke/OJWNFSN8rtaG2RGdLS9RMMWJiw18QdwD6XgkPpFe+JO4opMTo7NFxMjDgBoVxPGebqimTrpaNkB+NsybqQUTw/2NxO2NlSqLKJrDbEKaNJ7CqcHgaLEi0TCVSo3eKygIIu6Cu6bd46tG+lwf4YXh0xxiyvYdcYbs+II+2s/ueJcDtZ4nj8wK5oUm/6oGWSZM9p+pBlw6z7YB/8AHDH6o+sAgAA',
    'packages/Webkul/Admin/src/Console/Commands/CrmManagedBackupCommand.php' =>
        'H4sIAAAAAAACA6VWXW/bNhR996+4BQyI6iwne3VqB66XYsEWJLAbDEMcGJREWZwpSiCptGma/75LUbJkxc0azEBii7xfPOfeQ304L9JiMJA0Y7qgEYO/WLgrxXoeZ1yuF7nUuWD4nWVUxvpsMCg1g0shStymhvUtzvr7q7IocmXWn2hEY6bXc2W4ptLZHeRaMfXAIzRZqOwjjXZlcR3+wyKzMrmiW1Zv/5TjkhkmDc/lzzldF0xRa07F74wKk+7dBpGgWgPaXFGJRcQufn1YYF8xT4z77nnwNAD8FCo3WDeLYaj5FlEoFYMpeJHKJmHlH2QuGjwFQUwNDalmQS7F47N31g+BoEWKF7Y8G2ShGMI6ggemePI4gowrlasRKGYolyOwZSkW5SoGCovlFbiMYwzsIpeh4BEkpYyqkCk6CEaqPft5BQ4YptXjqGt8HHAYqmbliPUxXmGYd1crJ38CXBp42kcYNmBdI1aIBgnzXPgwNCnXwSyvQCLeAaKef9a675j1euWIk8nH+eKP25uOjzZUWSKmkGLrZowYVbJu0Jgj3lh1VZA2isutD1EuE74llvMgpSpmEpfHNRd7D28E2p13U1CTEo8WxUmh+ANSfGJdnYP2/G7CkCV51VFbkYekzT/+7XJ5sfh8vfx7s7q4mS/n+HPstWGC9+NvvPB8OJ/A3X3dD1VEx2swq89KLFCjHtrn4Dn2oFmGmGeloHzsweTl5i/N0Vozv5PTIF4tsVUVUR7bU9UKMZlEVAjSmRpE687rDYwH09lhofcdqKqweWmK0mBgpCYj++humfjdouyHJ0BcKe+mU9BMJJPJ6naxuFit/F7F1TlSlX8Byb7AellK2yAXXyPmOrFJjXh7Nziv0tAU2vPAlm6pGHu9gp8P6xnSxDD1/9k+hLpSEdvUVCn6uHmgomSauIeYJwlxaUdNt/m9IkuNok6aOCNIZNP7WLDtavcz9C0/JOGCZdXw2BUs6NSHD70NWm/06aMqSvmDbYwm293pPZyfgyyFOMLdu9bl+3d4B1xvbBbSrPp22a5o/q27OkW2T9/KsDevU9WMhlSVYHhMd9j1hmXljkrA5ixhl+scFeC/yFYsQ+W3hz2Qw2DmdL6t96zvV8vtkulS2G5vl4IZyop4JH2fuHQaaHULe9MHQroSB8Fe/Hw4gV83p6en9q8/MK16lBFeqLpWj0YQQqZSqrnAGRjbGbVvGu0xcKRfAO7Vm9VsH3F56RA+GqYr85fEHjFv5GJzXEGOeDgyKpF29o6nI5Z71Bu7A14OHe5HLQl9ctyVxmWSkwbJm/lqZVH8QQ9UwuXqOtbGdUTBJSOeu4Ebha6C1p6vtieeplTyUBRbh2eIqIlSIOvPdmRoKPBWZ82s9Gvat01CucA3pObS2TsEsy0zV9hQWCE53ihvJ3If3VnvH/G2sW96L/h502D0wFOsqESyheAox6wa7IbkT/PLPys+foBDf/oOKLHOt8uLDiUD9/958C8wnG0M6AsAAA==',
    'packages/Webkul/Admin/src/Console/Commands/CrmManagedEmailSyncCommand.php' =>
        'H4sIAAAAAAACA6VUbWvbMBD+7l9xg4JtqLvuq7OklJCywrJBvTJGU4oiX2JRWTZ62RpG/ntPtpsXp8s6JohNrHvunue5kz5e1EUdBIqVaGrGEb7j/NHJ2WVeCjUbV8pUEuldlkzlZhAEziBcS+lom1nsRwz6+5mr60rb2RXjLEczu9RWGKbauL1aGeqfglPIWJdfa9TMikox+QmZtEW3SfW5ZMYAxUyZYkvMJyUTMlsp3jEAfLJIVKH7H/wOgFatK4vcYg4nRiyJmtMIQwi5LlP0KRJDOZKyTRoO+iDizrWoPScPu3EKpitoioNHgi+tkVc6h2rLHoqG/hklbDO6uRQcFk7xJlVBMInREclw0qaIUxDKNklaRX6dGMu05zeEgowtMbLaYTzYBrTgZNQFHquUppPp5fXnh+zHl/EphJlQj7pS1C0joLEIclE6ycRZGHdy/LJ6tcOoKcqr3Jvb9TpNOZMyCstV0qRJvV/hDskGUzlbO0soq0UZbaDt5yjuh+eu1UCAiHyJIYp2HYBk400M7+HDw/n5uf/tEvdLLCBq6b4bDsGgXKRpdjseT7Is7qnas3NBOmiC3m7ni76LFMKdqVmyJZNn4Snc3Z9uRfXENpVtIUwyQq0rHR1P9gpaI8276vRdEavbm8l+1Hrfl+3cOE5n0vy30Dnqggbpr1o7nVIofJHZ79memK5Z2yRr4MzyAqLZt0JXv9hc0hHCJ47N2e039aChB9a9VfhhxzZFk9ES7ZRcpKslig8j78JNaAjD0Q6Sjo6/7u4PMf809HvonuEa/f0c7Vj0akO6wfuDpqMtOpi3ddA+18Ez3VajHH4GAAA=',
    'packages/Webkul/Admin/src/Console/Commands/CrmOperationalAlertsCommand.php' =>
        'H4sIAAAAAAACA3WQTWrDMBCF9z7FLAJOIMkB7KYlmFK66sKULpoQFGlii+jHaKRQKLl7ZVshaWhnI9nvzTdP8/DUtV2WGaaROsYRPnB/DGqzFlqaTWUNWYXx1JoZQWWWBUJ4VSpEmXm8d5SD/otRoztJjrSpnH7r0DEvrWFqrdD5pEUsV4wI/rBQIgN+eYwRIH1n3xnE6pz1yD0KmJBsYqTgEFaQc6cLe0HRgg2ovLzvEUjcya439V0vaPoWBHtNAS0y5ds5kLf8OActiaRp5tCHEkyzJoJG/jIOGCeEvZIcDsHwAd1Gr8Lp/xuAyUiYFSCNHxjj+/qaOKSgfAyYXIvHJgWdzsqrzbcyStIc7DS/mZPCFcBimBOu8mUCfsYtYYSInXW70In+mm+XOUTZqhOKW+vlX76dpUf25TAu3AChOhRF/V5Vz3U9Jjpn5+wH8OyreF0CAAA=',
    'packages/Webkul/Admin/src/Console/Commands/CrmProductionOperationsCheckCommand.php' =>
        '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',
    'packages/Webkul/Admin/src/Console/Commands/CrmQueueHeartbeatDispatchCommand.php' =>
        'H4sIAAAAAAACA21QyU7DMBC9+yvmgJT2UD7AYREKSMANRYhLL44zqa16CfZYgFD/nUmaHijMwdb4zbzFV7ejGYUIymMelUZ4w25f3Pau9zZsmxhydMi39yr0uRaiZIQn5wrDivB8op7xXxzPscvbJvmXggUfUSXqUBG/Mpl2Kmf4A95b9kLaLKSAn4SsDksvvgVwjSkSasIeLrLdsZuSEK6h0slLg8qRke8T7aZf6Kr6fK/HrJMdycYwbZ50QYE5eQEyfPiSCToEzXGL59XuiwGEWQA+YtpjumT+o0DpnNUwlKBnZsOeHa7WEmygeeIYYKr/PkbKk+PVenMTwzywqnocVHFUrReZqRJy6gAZ3SBl+9o0D217THkQB/EDmme/wt0BAAA=',
    'packages/Webkul/Admin/src/Console/Commands/CrmSchedulerHeartbeatCommand.php' =>
        'H4sIAAAAAAACA32S3WrCQBCF7/cp5kKIQusDxKqUNGBBKBikF7UXm83objvZhP2RQvHdu5tEQQvOTTacM9+eGfZp2cqWMc1rtC0XCO9YfnvaPVe10rus0bYhDN+65rqyM8a8RXgl8kHmDm8ds06/YhRojkqg3WWmfmvRcKcazWmFnJwcxMAVxK2F4CmExMoTmuAwrkTuBjbgj8MQAoZ/9ssgVGsah8JhBSOrDiGUNwhzSISpU9ldktozMpndtlRohVFtzBSbNigaU4GTCGtu+BEJLs0gz4GmgdODfElKwN5r0RFkiEU4vjMpjPpMkxSUdh2kHyPWoD0urBdhY3Z8UWLdoaZpka3yl+063zxc9ST/pyjRfHHieppcWz8S2ViXwHwBB3TxHB/FeALLFLQn+ry4J8P0sQyGfWuwSPsQYptleVH0Sz6xE/sDsqR2Yl4CAAA=',
    'packages/Webkul/Admin/src/Http/Middleware/CrmSecureUploadMiddleware.php' =>
        'H4sIAAAAAAACA5VUTY/aMBC951dMpUg4FModFrbVqlV72Eu3HwdAyDgTYq1JUn/woYr/XjtOQgjRSp1cHOfNe88z4zw8FmkRBBndoyooQ/iN21cjVp/iPc9WX7UuVs88jgUeqcRZEBiF8CRyZdybe/kmhLFQqtGjv+Mfg0r3f/xZiJzGGH/h4j79FxU8pprnWWv5+cSwcAsPv3H3gvLAGarVk9y/ILOWPH+1b90yQZWCzufreYK/AdgozFZwBonJmFOCzYblmdLSME0KmWtkGmPoF4Hw4L3mMirJPOUl6GVOaWalSVUjCKVfjOqSQpjhSbeJXPAEyLsGPF5wRQbUVWAyHERRC+hCojYy80SkzolmDaZy5iLJJVKWAgl1ytV4kQiqNWbkqkSFcK1SxMpQBWHCUcQwX7iVwK60lufOjouKvClTs0TiWWY3KRdgVDtTqx+pzI90K2xVsJ6CqEdAOxz0jMx0euQ6fUal6M6eYXmXWdq7nqlRGS92qKs8Eo3u8tZdz33VfasRFWwyHMLHCkelpOcHO3U8242gfVEWMJz4aZL8YMt2Hae6YWWu74kageeAsJCY8BPMYTCIpp6/M1jWlTJCW8hyPeubi5Kx7Pwrnssa2d6Zu8aH7vdhWRrJuROFRyDeS+TzpzXgw8A+bqslWg+6VwBuLyDNGObJTS36+l+dYlm6WDsbJUV3rFAodAJcbcpaVErRG5Twfg7du1EmjfyR/2cMPGPd/kvwDxmHe+x5BQAA',
    'packages/Webkul/Admin/src/Jobs/CrmQueueHeartbeatJob.php' =>
        'H4sIAAAAAAACA41RXUvDMBR976+4D4Nu0Ini2+Ymfg0VRIaMPVgf0vTOBNOky8cExf9umnZjtWOYp+ae03PPObm4LFkZRZIUaEpCEZaYfTiRXuUFl+mjysw4ipxBeBDC+RGxmF47k84dOiSZwPFf9EZJqwm1DSd9YcqJPHz/k3uNC8nXB+gz5WROLFcyeLjl3rKl7KCNWvBBWgwLltyywx6azag5EfwLzZPKUZia1irDUzacoklvdPFcetnKCBH3SIRlDejLooIYA54ThD2qbYbE+iqBF6XAAqU1sNdKAu3Y0XcE/lT79xMm0A2TwO4dvEonQtApXSY4BS4t9KzmaGACZ+MuxAtUznrw/LSLuuBspnTAT9vSKydp1QUwInOB/SP9QI+F62AEG8XzoFLHrU4DDqfGUV+06e+Q6hyRHY3mi7vFXdLix6Eb+FT6AzUUWJRaGR+fbZ/kJG7/8BpTJSWGLDFMpuCvK/7ej9eV0EmOK+KEjQcJ1JPA6VnGzXAaBnA5gnhLe9tpD+o+f6Kf6BdL+eyzbwMAAA==',
    'packages/Webkul/Admin/src/Services/CrmBackupObjectStorageService.php' =>
        'H4sIAAAAAAACA51UW2/TMBR+z684k6o5kdbywFtHNw3EJF4AMS4PFE1OctKaOLHlS7fC9t+x46R1M2Da/FCl9rl93znne3Uu1zJJWtqglrRA+IZ5bfnyomxYu7xCtWEF6tMksRrhHefWXVODy0vGUW+1wSb6vCipNKhOx8ZXVkqhzPKSFrREvbwyQtEVBrtPtjWswbe3BUrDROtyFZxqDW9U85oWtZUf8p9YmN6pLyn5nYA70uacFVDZtvC+0DClhEq1UaxdwYSLgvKP1KyzOZyHy84tOPszKZmu3zv0sIBCtBVbpaRQzVQqUdou5lRIVNR/6Vne1TMTXUHX3pVkrt4hGKsgPQKmr0OqdBc8g7s7cHdNfLVYLICQLCrGH4XGqhZay/np7uE+OSzYFTsxa6anZ/5fOoqc7R0nUmHFbp19z0n2ZJQhAjmBziO8aRLnUNgIg55mlyfUEpyczwuSzdyPi6nRD1ka9SQK4YpD2jj3ytXRRkYuhMofkLyzdxRWlGscs2jWStxAizcPxisln1lJa3CjSg002OS2phBgARc15eA8bA0BPugwdrMYcdQOo7aj1GEIum5Mz6Q1acTPyYD0BL6TDdMsZ5yZLYHFGRCp2MZtC/mRjdE8huiL5IKWA4gaR7XDiq4oP0AQUOy+oHKLyvkYSlVwoXFg++8EHMDFW6aNjhF3k9+/avYLD9+OfP+8fHQv+8l4Sju/omIVq6lmYGuraDvw8CgJEYp+66LigllvMpaZsENWYZk6ZcmF4CNZ6ePtpeB52pIdsHB8HLbr2avcB+1oJ+QQYRi+PcROWAYd9ZvrgD5Q+hHqbjXbTkx9hr1eTPXLfwldL+vzeW4ZL9OUKkW3T4fmu4wk+293d6k6bAHUQMJ98gdVzzNICgcAAA==',
    'packages/Webkul/Admin/src/Services/CrmBackupRetentionService.php' =>
        'H4sIAAAAAAACA61XX2/bNhB/96dgAaOSFsvNigEb7NpBmjRAH7YOSYAgswODlk82F4kUSCqZW/i770hKsiTbyepVDyHNO97d73j/8uEsW2WdDqcpqIxGQO5g/pgn0/NFyvj0BuQTi0ANO51cAfmcJDkeUw3TmzzLhNTTKxrRBajpFUtgaJn+Ytm5jFbsCX93ooQqRS5k+pFGj3l2DRq4ZoIXgjvfOgS/LJ8nLCJxziNDJDTLkrUfDAiVkq4ti2M0X3fBJERayDUZEV9pyfgyIJHgMVv6FZf5vEim4YrKBXDk6c+tCf3qutdrcCs8o0uYZVSvfA9NeJdJ9oRY3xkx7rLygupOgPDKPYuJ/4YwNUPh/tbAIKjZbT4JOpecTLxHyLRHRmNy2iPeAhL0y6L6PV9rULNYQnn2MKykbLZKuzH6XKETlomY17T2Lz9ff7q4/XJ9P7v59Of59Tlu+94WRPhT/yvLvICcDcikJjpX+KC+k9ojMS+dS7q0R8rtPDAW+YYp1SwF35ygoNOAfGgRaEGoO6rLxTNajH/9YFh7UsqS9UWuRRwj1TCF40hkGAPhWOXzS7pWvs+4rt7ZwsmkWOQ2YkKRgaRmp8pnfgTIZlYu/l0rr0d+/iWo63wGeHxJ6R3Sj9PqJNvF6P2toTYVXK9e0vu7YThOcSHbrRby+4buNVD5kup7pB+n2Um2i9H7a/PRDQvqqwdb4f72aYGgfezEt085PIPSH9e36wwKWkWMhQQarUgRz4Qq0jWZ3c7IrolVvDzdU9suqJwLPhhEKErDlRTpLTIrTdOsHudOrA31YUu2M6yrV0yFY6YuqaZzquALx/JW3sP8L449MiBenCeJVwNSLy8KMD/rqCdWxcNOndlxT8GIxmiZw3CX2TzRxJq0n2mza5F1XTheavRBLYH3G3OUfOO+N6NR4ZR9cjFKNeOvCLOxZt/BGoyhkVLteyK881ov1oZVrxEBefuWVK/gKBO7HvB/g+WHed6lyC6a+zB9DU2j9DThFKSJ2xwA1GL6cZBMeu9B9BqeejlrwnGUiV0PgGmw/B8o+zpz0dHxxmmtXtmO7s6+u04Z7AW4mknB8Tmh2FdTm6xW3NbL2K7XzXQ3GDhUBedepxa4T072eNKhPxk51f/Bi+Ww1JzpqskpEjkvx5WAhJXy5lTXGK4OsLTnLWfqlqnoOYVp7WG1VdbLQck6aUDmQiStAdZVczsZz+AfprTytzMzdhtDODQ5xjRRsH8axJnOTFbwXJvA60OWzR5kCsfYw3n53qa8mqj+bn0a5+9WK/1boTtGxClZgjYd8w/8x8L3UtDUdLm+YWmndbek4l0coZ0DfSvNtEezzhYQiQW4w15h8IBwbAutVlnY7Ru/Bwi4lD2puuxM4DN5D+TszKGrWbPBdOA0SXaQWURRIlTDo5siLDadfwFaq7UMRg0AAA==',
    'packages/Webkul/Admin/src/Services/CrmOperationalAlertService.php' =>
        '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',
    'packages/Webkul/Admin/src/Services/CrmOperationalHealthService.php' =>
        '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',
    'packages/Webkul/Admin/src/Services/CrmSecureUploadService.php' =>
        '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',
    'tools/run_crm_queue_worker_v2.cmd' =>
        'H4sIAAAAAAACAzWOwQ7CIBBE73zFponHtmijhyYYfwVhsaS0S2GJnvx2SdXTvDdzmRuaiYCcExk5kNFBGAu9hebwtlF2XSPGhJl1YhGnCDV91itsBQuOT0oztO0uyqLTJXD1HBCjGipx8pi/5Bekwup4ktUW/dobNVykFL8NeoYz9CvdE+oZrmsJ4kFM8D/wAUJx2/utAAAA',
];

$payload = [];

foreach ($encodedPayload as $relative => $encoded) {
    $contents = gzdecode(base64_decode($encoded, true) ?: '');

    if (! is_string($contents) || $contents === '') {
        fwrite(STDERR, "PATCH GAGAL: payload rusak: {$relative}\n");
        exit(1);
    }

    $payload[$relative] = str_replace(["\r\n", "\r"], "\n", $contents);
}

function crmV2Path(string $root, string $relative): string
{
    return $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function crmV2Read(string $path): string
{
    $contents = file_get_contents($path);

    if ($contents === false) {
        throw new RuntimeException('Tidak dapat membaca '.$path);
    }

    return str_replace(["\r\n", "\r"], "\n", $contents);
}

function crmV2Write(string $path, string $contents): void
{
    $directory = dirname($path);

    if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
        throw new RuntimeException('Tidak dapat membuat folder '.$directory);
    }

    $temporary = $path.'.tmp-'.bin2hex(random_bytes(4));

    if (file_put_contents($temporary, $contents, LOCK_EX) === false || ! rename($temporary, $path)) {
        @unlink($temporary);
        throw new RuntimeException('Tidak dapat menulis '.$path);
    }
}

function crmV2Run(string $root, array $arguments): array
{
    if (! function_exists('exec')) {
        return [null, ['PHP exec() tidak tersedia.']];
    }

    $parts = [escapeshellarg(PHP_BINARY)];
    foreach ($arguments as $argument) {
        $parts[] = escapeshellarg((string) $argument);
    }

    $output = [];
    $code = 0;
    $previous = getcwd();
    chdir($root);

    try {
        exec(implode(' ', $parts).' 2>&1', $output, $code);
    } finally {
        if ($previous !== false) {
            chdir($previous);
        }
    }

    return [$code, $output];
}

function crmV2LintContents(string $relative, string $contents): void
{
    if (! str_ends_with($relative, '.php') || ! function_exists('exec')) {
        return;
    }

    $temporary = tempnam(sys_get_temp_dir(), 'crm-v2-lint-');
    if ($temporary === false || file_put_contents($temporary, $contents, LOCK_EX) === false) {
        throw new RuntimeException('Tidak dapat membuat file lint sementara.');
    }

    $output = [];
    $code = 0;

    try {
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temporary).' 2>&1', $output, $code);
    } finally {
        @unlink($temporary);
    }

    if ($code !== 0) {
        throw new RuntimeException("PHP lint gagal: {$relative}\n".implode("\n", $output));
    }
}

$required = [
    'routes/console.php',
    'config/app.php',
    'config/crm-hardening.php',
    'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php',
    'packages/Webkul/Admin/src/Services/OperationsDashboardService.php',
    'packages/Webkul/Admin/src/Resources/views/operations-dashboard/index.blade.php',
    'packages/Webkul/Admin/src/Console/Commands/CrmBackupCommand.php',
];

foreach ($required as $relative) {
    if (! is_file(crmV2Path($root, $relative))) {
        fwrite(STDERR, "PATCH GAGAL: file wajib tidak ditemukan: {$relative}\n");
        exit(1);
    }
}

$targets = $payload;

try {
    $operationsRelative = 'packages/Webkul/Admin/src/Services/OperationsDashboardService.php';
    $operations = crmV2Read(crmV2Path($root, $operationsRelative));

    if (! str_contains($operations, CRM_PRODUCTION_OPERATIONS_V2)) {
        $returnNeedle = "        return [\n            'role' => \$role,";

        if (substr_count($operations, $returnNeedle) !== 1) {
            throw new RuntimeException('Preflight OperationsDashboardService tidak cocok.');
        }

        $operations = str_replace(
            $returnNeedle,
            "        /* ".CRM_PRODUCTION_OPERATIONS_V2." */\n"
            ."        \$operationsHealth = app(\\Webkul\\Admin\\Services\\CrmOperationalHealthService::class)->summary();\n\n"
            .$returnNeedle."\n            'operationsHealth' => \$operationsHealth,",
            $operations
        );
    }

    $targets[$operationsRelative] = $operations;

    $viewRelative = 'packages/Webkul/Admin/src/Resources/views/operations-dashboard/index.blade.php';
    $view = crmV2Read(crmV2Path($root, $viewRelative));

    if (! str_contains($view, CRM_PRODUCTION_OPERATIONS_V2)) {
        $position = strrpos($view, '</x-admin::layouts>');
        if ($position === false) {
            throw new RuntimeException('Penutup layout Operations Dashboard tidak ditemukan.');
        }

        $fragment = $payload['__dashboard_fragment__'];
        unset($targets['__dashboard_fragment__']);
        $view = substr($view, 0, $position).$fragment."\n".substr($view, $position);
    } else {
        unset($targets['__dashboard_fragment__']);
    }

    $targets[$viewRelative] = $view;

    $providerRelative = 'packages/Webkul/Admin/src/Providers/CrmHardeningCoreServiceProvider.php';
    $provider = crmV2Read(crmV2Path($root, $providerRelative));

    if (! str_contains($provider, CRM_PRODUCTION_OPERATIONS_V2)) {
        $bootNeedle = "    public function boot(): void\n    {\n";
        if (substr_count($provider, $bootNeedle) !== 1) {
            throw new RuntimeException('Preflight boot() provider tidak cocok.');
        }

        $boot = <<<'PHP'
        /* CRM_PRODUCTION_OPERATIONS_V2 */
        $this->app['router']->pushMiddlewareToGroup(
            'web',
            \Webkul\Admin\Http\Middleware\CrmSecureUploadMiddleware::class
        );

        if (
            $this->app->environment('production')
            && config('crm-production-operations.production.force_https', false)
        ) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

PHP;
        $provider = str_replace($bootNeedle, $bootNeedle.$boot, $provider);

        $commandsNeedle = '$this->commands([';
        $commandsStart = strpos($provider, $commandsNeedle);
        if ($commandsStart === false) {
            throw new RuntimeException('Registrasi command provider tidak ditemukan.');
        }
        $commandsEnd = strpos($provider, ']);', $commandsStart);
        if ($commandsEnd === false) {
            throw new RuntimeException('Penutup registrasi command provider tidak ditemukan.');
        }

        $commandLines = <<<'PHP'
                \Webkul\Admin\Console\Commands\CrmSchedulerHeartbeatCommand::class,
                \Webkul\Admin\Console\Commands\CrmQueueHeartbeatDispatchCommand::class,
                \Webkul\Admin\Console\Commands\CrmManagedEmailSyncCommand::class,
                \Webkul\Admin\Console\Commands\CrmManagedBackupCommand::class,
                \Webkul\Admin\Console\Commands\CrmBackupRetentionCommand::class,
                \Webkul\Admin\Console\Commands\CrmOperationalAlertsCommand::class,
                \Webkul\Admin\Console\Commands\CrmAttachmentStorageAuditCommand::class,
                \Webkul\Admin\Console\Commands\CrmAttachmentMigrateCommand::class,
                \Webkul\Admin\Console\Commands\CrmProductionOperationsCheckCommand::class,

PHP;
        $provider = substr($provider, 0, $commandsEnd).$commandLines.substr($provider, $commandsEnd);
    }

    $targets[$providerRelative] = $provider;

    $consoleRelative = 'routes/console.php';
    $console = crmV2Read(crmV2Path($root, $consoleRelative));

    if (! str_contains($console, CRM_PRODUCTION_OPERATIONS_V2)) {
        $console = preg_replace(
            '~\n?/\* CRM_DAILY_FULL_BACKUP_SCHEDULE_V1.*?appendOutputTo\([^;]+;\s*~s',
            "\n",
            $console,
            1
        ) ?? $console;

        $console = preg_replace(
            "~Schedule::command\\(['\"]my-email:sync['\"]\\)\\s*->everyFiveMinutes\\(\\)\\s*->withoutOverlapping\\(\\s*10\\s*\\)\\s*;~s",
            '',
            $console,
            1
        ) ?? $console;

        $schedule = <<<'PHP'

/* CRM_PRODUCTION_OPERATIONS_V2
 * Application schedules. The operating system must run artisan schedule:run
 * every minute, and a separate long-running queue:work process must be active.
 */
$crmOperationsTimezone = (string) config('app.timezone', 'Asia/Jakarta');

Schedule::command('crm:health:scheduler')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('crm:health:queue-dispatch')
    ->everyMinute()
    ->withoutOverlapping(2);

Schedule::command('crm:email-sync-managed')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->appendOutputTo(storage_path('logs/crm-email-sync.log'));

Schedule::command('crm:backup-managed --database-only')
    ->dailyAt((string) config('crm-production-operations.backup.daily_database_time', '02:00'))
    ->timezone($crmOperationsTimezone)
    ->withoutOverlapping(360)
    ->appendOutputTo(storage_path('logs/crm-database-backup.log'));

Schedule::command('crm:backup-managed')
    ->weeklyOn(
        (int) config('crm-production-operations.backup.weekly_full_day', 0),
        (string) config('crm-production-operations.backup.weekly_full_time', '03:00')
    )
    ->timezone($crmOperationsTimezone)
    ->withoutOverlapping(720)
    ->appendOutputTo(storage_path('logs/crm-full-backup.log'));

Schedule::command('crm:backup-retention')
    ->dailyAt('04:00')
    ->timezone($crmOperationsTimezone)
    ->withoutOverlapping(30);

Schedule::command('crm:operations-alerts')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);
PHP;

        $console = rtrim($console)."\n\n".$schedule."\n";
    }

    $targets[$consoleRelative] = $console;

    $appConfigRelative = 'config/app.php';
    $appConfig = crmV2Read(crmV2Path($root, $appConfigRelative));

    if (! str_contains($appConfig, 'CRM_APP_TIMEZONE_V2')) {
        $timezoneCount = 0;
        $appConfig = preg_replace(
            "/'timezone'\\s*=>\\s*'[^']+'\\s*,/",
            "'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'), // CRM_APP_TIMEZONE_V2",
            $appConfig,
            1,
            $timezoneCount
        );

        if (! is_string($appConfig) || $timezoneCount !== 1) {
            if (! preg_match("/'timezone'\\s*=>\\s*env\\(/", $appConfig)) {
                throw new RuntimeException('Preflight timezone config/app.php tidak cocok.');
            }

            $appConfig = str_replace(
                "'timezone' => env(",
                "'timezone' => env( // CRM_APP_TIMEZONE_V2\n            ",
                $appConfig
            );
        }
    }

    $targets[$appConfigRelative] = $appConfig;

    $hardeningRelative = 'config/crm-hardening.php';
    $hardening = crmV2Read(crmV2Path($root, $hardeningRelative));

    if (! str_contains($hardening, 'CRM_GFS_RETENTION_V2')) {
        $count = 0;
        $hardening = preg_replace(
            "/'retention_days'\\s*=>\\s*\\d+\\s*,/",
            "'retention_days' => 2555, // CRM_GFS_RETENTION_V2: deletion is managed by crm:backup-retention",
            $hardening,
            1,
            $count
        );

        if (! is_string($hardening) || $count !== 1) {
            throw new RuntimeException('Preflight retention_days tidak cocok.');
        }
    }

    $targets[$hardeningRelative] = $hardening;

    $backupControllerRelative = 'packages/Webkul/Admin/src/Http/Controllers/System/CrmBackupController.php';
    $backupControllerPath = crmV2Path($root, $backupControllerRelative);

    if (is_file($backupControllerPath)) {
        $backupController = crmV2Read($backupControllerPath);
        $backupController = str_replace(
            "Artisan::call('crm:backup')",
            "Artisan::call('crm:backup-managed')",
            $backupController,
            $manualBackupCount
        );

        if ($manualBackupCount > 0 || str_contains($backupController, "Artisan::call('crm:backup-managed')")) {
            $targets[$backupControllerRelative] = $backupController;
        }
    }

    $backupStatusRelative = 'packages/Webkul/Admin/src/Services/CrmBackupStatusService.php';
    $backupStatusPath = crmV2Path($root, $backupStatusRelative);

    if (is_file($backupStatusPath)) {
        $backupStatus = crmV2Read($backupStatusPath);
        $backupStatus = str_replace(
            "(int) config('crm-hardening.backup.retention_days', 14)",
            "(int) config('crm-production-operations.backup.keep_daily_days', 14)",
            $backupStatus
        );
        $targets[$backupStatusRelative] = $backupStatus;
    }

    $attachmentRoots = [
        crmV2Path($root, 'packages/Webkul/Admin/src'),
        crmV2Path($root, 'packages/Webkul/Invoice/src'),
    ];
    $attachmentPatched = 0;

    foreach ($attachmentRoots as $attachmentRoot) {
        if (! is_dir($attachmentRoot)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($attachmentRoot, FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if (! $file->isFile() || strtolower($file->getExtension()) !== 'php') {
                continue;
            }

            if (! preg_match('/(Attachment|PaymentProof|IdentityDocument|Vendor.*Image|InternalChat)/i', $file->getBasename())) {
                continue;
            }

            $contents = crmV2Read($file->getPathname());
            $updated = str_replace(
                ["Storage::disk('local')", 'Storage::disk("local")'],
                "Storage::disk((string) config('crm-production-operations.attachments.disk', 'local'))",
                $contents,
                $count
            );

            if ($count < 1) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            $targets[$relative] = $updated;
            $attachmentPatched += $count;
        }
    }

    foreach ($targets as $relative => $contents) {
        if ($relative === '__dashboard_fragment__') {
            continue;
        }
        crmV2LintContents($relative, $contents);
    }
} catch (Throwable $exception) {
    fwrite(STDERR, "PATCH GAGAL PADA PREFLIGHT: ".$exception->getMessage()."\nTidak ada file yang diubah.\n");
    exit(1);
}

$backupRoot = crmV2Path(
    $root,
    'storage/app/private/patch-backups/crm-production-operations-v2-'.date('Ymd-His')
);
$originals = [];
$created = [];

try {
    foreach ($targets as $relative => $contents) {
        if ($relative === '__dashboard_fragment__') {
            continue;
        }

        $path = crmV2Path($root, $relative);

        if (is_file($path)) {
            $originals[$relative] = (string) file_get_contents($path);
            $backupPath = crmV2Path($backupRoot, $relative);
            $backupDirectory = dirname($backupPath);
            if (! is_dir($backupDirectory) && ! mkdir($backupDirectory, 0775, true) && ! is_dir($backupDirectory)) {
                throw new RuntimeException('Gagal membuat folder backup patch.');
            }
            if (! copy($path, $backupPath)) {
                throw new RuntimeException('Gagal backup '.$relative);
            }
        } else {
            $created[] = $relative;
        }

        crmV2Write($path, $contents);
        echo "[WRITE] {$relative}\n";
    }

    crmV2Write(
        $backupRoot.DIRECTORY_SEPARATOR.'manifest.json',
        json_encode([
            'patch' => CRM_PRODUCTION_OPERATIONS_V2,
            'created_at' => date(DATE_ATOM),
            'overwritten' => array_keys($originals),
            'created' => $created,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}'
    );

    foreach ([
        ['artisan', 'config:clear'],
        ['artisan', 'cache:clear'],
        ['artisan', 'view:cache'],
        ['artisan', 'view:clear'],
    ] as $arguments) {
        [$code, $output] = crmV2Run($root, $arguments);
        if ($code !== null && $code !== 0) {
            throw new RuntimeException(implode(' ', $arguments)." gagal:\n".implode("\n", $output));
        }
    }
} catch (Throwable $exception) {
    foreach ($originals as $relative => $contents) {
        @file_put_contents(crmV2Path($root, $relative), $contents, LOCK_EX);
    }
    foreach ($created as $relative) {
        @unlink(crmV2Path($root, $relative));
    }
    crmV2Run($root, ['artisan', 'config:clear']);
    crmV2Run($root, ['artisan', 'view:clear']);

    fwrite(STDERR, "\nPATCH GAGAL: ".$exception->getMessage()."\nSemua perubahan file dipulihkan.\n");
    exit(1);
}

[$migrateCode, $migrateOutput] = crmV2Run($root, ['artisan', 'migrate', '--force']);

if ($migrateCode !== null && $migrateCode !== 0) {
    fwrite(STDERR, "\nFILE TERPASANG, TETAPI MIGRATION GAGAL:\n".implode("\n", $migrateOutput)."\n");
    fwrite(STDERR, "Perbaiki koneksi/schema lalu jalankan: php artisan migrate --force\n");
    exit(1);
}

echo "\nPATCH BERHASIL.\n";
echo "- Scheduler, queue worker, backup, dan email sync health terpasang.\n";
echo "- Daily DB backup + weekly full backup + GFS retention terpasang.\n";
echo "- Index query utama ditambahkan secara kondisional.\n";
echo "- MIME/signature validation dan opsi ClamAV terpasang.\n";
echo "- Alert backup/queue/stock/asset terpasang.\n";
echo "- Attachment storage adapters diperbarui: {$attachmentPatched} pemakaian disk.\n";
echo "- Backup patch file: {$backupRoot}\n\n";
echo "LANJUTKAN: salin nilai dari tools/ENV-production-operations-v2.example ke .env, lalu jalankan checker.\n";

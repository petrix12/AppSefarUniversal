<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Usuarios reales
        User::create(['user_id' => '52','name' => 'Pedro Bazó','email' => 'bazo.pedro@gmail.com','password' => '$2y$10$LoJz0JHclvdush.3.0MLjOoBjHwRtgDswAhUFmGmClBQVWTmfFhfW','password_md5' => '$2y$10$LoJz0JHclvdush.3.0MLjOoBjHwRtgDswAhUFmGmClBQVWTmfFhfW','created' => '2021/3/9'])->assignRole('Administrador');
        User::create(['user_id' => '2','name' => 'Pedro Bazo','email' => 'documentalista@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/5/13'])->assignRole('Administrador');
        User::create(['user_id' => '3','name' => 'Angel Rosales','email' => 'arosales@sefarvzla.com','password' => '71d0e9eca2858092eafe0cb0cbd7f70d','password_md5' => '71d0e9eca2858092eafe0cb0cbd7f70d','created' => '2020/5/13'])->assignRole('Genealogista');
        User::create(['user_id' => '4','name' => 'Eliot Gonzalez','email' => 'egonzalez@sefarvzla.com','password' => 'b5e8405c1104a757ae97248a118e49b8','password_md5' => 'b5e8405c1104a757ae97248a118e49b8','created' => '2020/5/14'])->assignRole('Produccion');
        User::create(['user_id' => '5','name' => 'Juan Rondon','email' => 'admin.ventas2@sefarvzla.com','password' => 'df7012456378472fedfb6e54548d030d','password_md5' => 'df7012456378472fedfb6e54548d030d','created' => '2020/5/14'])->assignRole('Genealogista');
        User::create(['user_id' => '6','name' => 'Liliana Du Bois','email' => 'genealogista.dubois@gmail.com','password' => '92c607e2962320fc458e54ead0d4dd5a','password_md5' => '92c607e2962320fc458e54ead0d4dd5a','created' => '2020/5/14'])->assignRole('Genealogista');
        User::create(['user_id' => '7','name' => 'Jose Gonzalez','email' => 'organizacionrrhh@sefarvzla.com','password' => '6fba4c121d214bbbdf4097930a554f35','password_md5' => '6fba4c121d214bbbdf4097930a554f35','created' => '2020/5/18'])->assignRole('Genealogista');
        User::create(['user_id' => '8','name' => 'Juan Hernandez','email' => 'genealogista5@sevarvzla.com','password' => 'efae0cb925cb594abf1f9113e9f7a6ae','password_md5' => 'efae0cb925cb594abf1f9113e9f7a6ae','created' => '2020/5/22'])->assignRole('Documentalista');
        User::create(['user_id' => '9','name' => 'Miguel Herrera','email' => 'gerenciait@sefarvzla.com','password' => '76989e5238f359aeca43265a80e28ea9','password_md5' => '76989e5238f359aeca43265a80e28ea9','created' => '2020/5/23'])->assignRole('Administrador');
        User::create(['user_id' => '10','name' => 'Adriana Colmenares','email' => 'amcolmena@gmail.com','password' => '2f52b157e3e77a92cbe265631c5d82ed','password_md5' => '2f52b157e3e77a92cbe265631c5d82ed','created' => '2020/5/25'])->assignRole('Documentalista');
        User::create(['user_id' => '11','name' => 'Jose Rodriguez','email' => 'seguridad@sefarvzla.com','password' => '99feb44e73c4306785d8650b0c6da8b7','password_md5' => '99feb44e73c4306785d8650b0c6da8b7','created' => '2020/6/4'])->assignRole('Administrador');
        User::create(['user_id' => '12','name' => 'Ivanna Materan','email' => 'Ivannawmg.18@gmail.com','password' => 'fd30012870c03ddc8bc6ac287d58d50d','password_md5' => 'fd30012870c03ddc8bc6ac287d58d50d','created' => '2020/6/10'])->assignRole('Documentalista');
        User::create(['user_id' => '13','name' => 'Luz Armenta','email' => 'larmenta@sefarvzla.com','password' => 'e39097691f600e829ef3de7095e36737','password_md5' => 'e39097691f600e829ef3de7095e36737','created' => '2020/6/17'])->assignRole('Produccion');
        User::create(['user_id' => '14','name' => 'Daphne Rojas','email' => 'drojasefar@gmail.com','password' => 'b65f295a165b38ea8c4168444bcab797','password_md5' => 'b65f295a165b38ea8c4168444bcab797','created' => '2020/6/22'])->assignRole('Documentalista');
        User::create(['user_id' => '15','name' => 'Genesis Gonzalez','email' => 'ggonzalez@sefarvzla.com','password' => '552ed86ccdccfecb19af2783182ebc1f','password_md5' => '552ed86ccdccfecb19af2783182ebc1f','created' => '2020/7/13'])->assignRole('Genealogista');
        User::create(['user_id' => '16','name' => 'Kelly Alvarado','email' => 'kelly.alvarado@sefarvzla.com','password' => '1497d0e8adcc86c591ab1cc8312d13b3','password_md5' => '1497d0e8adcc86c591ab1cc8312d13b3','created' => '2020/7/15'])->assignRole('Documentalista');
        User::create(['user_id' => '17','name' => 'Juan Uzcategui','email' => 'admin@sefarvzla.com','password' => '46dfb1fd21d4e16401260f54d2b6a88a','password_md5' => '46dfb1fd21d4e16401260f54d2b6a88a','created' => '2020/7/28'])->assignRole('Documentalista');
        User::create(['user_id' => '18','name' => 'Jose Romero','email' => 'genealogista.romero@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/9/1'])->assignRole('Genealogista');
        User::create(['user_id' => '19','name' => 'Miguel Herrera','email' => 'gerenciait@sefaruniversal.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/9/1'])->assignRole('Administrador');
        User::create(['user_id' => '20','name' => 'Marco Pulgar','email' => 'marco.pulgar@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/9/7'])->assignRole('Genealogista');
        User::create(['user_id' => '21','name' => 'Juan Rondon','email' => 'admin.ventas@sefarvzla.com','password' => 'd49e0a7d5c6b70736040bd53e98ad9f5','password_md5' => 'd49e0a7d5c6b70736040bd53e98ad9f5','created' => '2020/9/15'])->assignRole('Genealogista');
        User::create(['user_id' => '22','name' => 'Crisanto Bello','email' => 'crisantoantonio@gmail.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/9/21'])->assignRole('Genealogista');
        User::create(['user_id' => '23','name' => 'Jose Perez','email' => 'josealejandro.perez@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/9/22'])->assignRole('Documentalista');
        User::create(['user_id' => '24','name' => 'Mayleth Reales','email' => 'asistentedeproduccion@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/9/22'])->assignRole('Produccion');
        User::create(['user_id' => '25','name' => 'Maria Mucino','email' => 'mmucino@sefarvzla.com','password' => '6e4383788a682d6f0c2c41f5833889bc','password_md5' => '6e4383788a682d6f0c2c41f5833889bc','created' => '2020/9/29'])->assignRole('Documentalista');
        User::create(['user_id' => '26','name' => 'Maria Ramirez','email' => 'genealogista4@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/6'])->assignRole('Documentalista');
        User::create(['user_id' => '27','name' => 'Pablo Mestre','email' => 'genealogista3@sefarvzla.com','password' => '0d0b257fe1b17e047277949d2a5f8124','password_md5' => '0d0b257fe1b17e047277949d2a5f8124','created' => '2020/10/7'])->assignRole('Genealogista');
        User::create(['user_id' => '28','name' => 'Pilar Cardenas','email' => 'genealogista2@sefarvzla.com','password' => 'f7531eef67a2f4dd8bb1033498b031ef','password_md5' => 'f7531eef67a2f4dd8bb1033498b031ef','created' => '2020/10/7'])->assignRole('Documentalista');
        User::create(['user_id' => '30','name' => 'Antonio Montero','email' => 'practicashistoria@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/7'])->assignRole('Documentalista');
        User::create(['user_id' => '31','name' => 'Elias Pino','email' => 'eliaspino@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/7'])->assignRole('Documentalista');
        User::create(['user_id' => '32','name' => 'Abelardo Bazo','email' => 'historiador@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/7'])->assignRole('Genealogista');
        User::create(['user_id' => '33','name' => 'Jesus Barreto','email' => 'jbarreto@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/7'])->assignRole('Documentalista');
        User::create(['user_id' => '34','name' => 'Emad Aboassi','email' => 'investigador2@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/7'])->assignRole('Genealogista');
        User::create(['user_id' => '35','name' => 'Yeison Diaz','email' => 'yeinsondiaz@sefarvzla.com','password' => 'fa9480d0c6cfc8181333111c444d76f1','password_md5' => 'fa9480d0c6cfc8181333111c444d76f1','created' => '2020/10/7'])->assignRole('Documentalista');
        User::create(['user_id' => '37','name' => 'Jinerson Ariza','email' => 'sistemas@sefarvzla.com','password' => '66d4e97efb8737b698def8a7e94e740a','password_md5' => '66d4e97efb8737b698def8a7e94e740a','created' => '2020/10/9'])->assignRole('Administrador');
        User::create(['user_id' => '38','name' => 'Jose Maldonado','email' => 'sistemasse@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/19'])->assignRole('Documentalista');
        User::create(['user_id' => '39','name' => 'Cora Chumaceiro','email' => 'cora.chumaceiro@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/19'])->assignRole('Documentalista');
        User::create(['user_id' => '40','name' => 'Belkis Espinal','email' => 'b.espinal@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/23'])->assignRole('Documentalista');
        User::create(['user_id' => '41','name' => 'Digitalizador Genealogico 3','email' => 'digitalizadorgenealogico3@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/27'])->assignRole('Documentalista');
        User::create(['user_id' => '42','name' => 'Digitalizador Genealogico 2','email' => 'digitalizadorgenealogico2@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/10/30'])->assignRole('Documentalista');
        User::create(['user_id' => '43','name' => 'Nelson Sanguinetti','email' => 'nelson.sanguinetti@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/11/25'])->assignRole('Documentalista');
        User::create(['user_id' => '44','name' => 'Elizabeth Avendano','email' => 'investigador1@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2020/11/26'])->assignRole('Documentalista');
        User::create(['user_id' => '45','name' => 'Andrea Rodriguez','email' => 'arodriguez@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/1/15'])->assignRole('Documentalista');
        User::create(['user_id' => '46','name' => 'Maribel Espinoza','email' => 'coord.genealogia@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/2/3'])->assignRole('Documentalista');
        User::create(['user_id' => '47','name' => 'Pedro Bello','email' => 'ppedrobello@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/2/17'])->assignRole('Produccion');
        User::create(['user_id' => '48','name' => 'onwardcreativesolutions','email' => 'onwardcreativesolutions@gmail.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/2/17'])->assignRole('Documentalista');
        User::create(['user_id' => '49','name' => 'Yelitza Marcano','email' => 'y.marcano@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/2/26'])->assignRole('Documentalista');
        User::create(['user_id' => '50','name' => 'Milena Cera Avenda','email' => 'mcera@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/2/26'])->assignRole('Documentalista');
        User::create(['user_id' => '51','name' => 'Rosalba Di Miele','email' => 'rdimiele@sefarvzla.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/3/9'])->assignRole('Genealogista');
        User::create(['user_id' => '52','name' => 'Rosalba','email' => 'rosalba@gmail.com','password' => 'b82aa3b51060c437c66ddb3d225e7aed','password_md5' => 'b82aa3b51060c437c66ddb3d225e7aed','created' => '2021/3/9'])->assignRole('Genealogista');
                               

        // Usuarios de prueba
        User::create([
            'name' => 'Prueba Genealogista',
            'email' => 'genealogista@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Genealogista');

        User::create([
            'name' => 'Prueba Documentalista',
            'email' => 'documentalista@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Documentalista');

        User::create([
            'name' => 'Prueba Producción',
            'email' => 'produccion@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Produccion');

        User::create([
            'name' => 'Prueba cliente',
            'email' => 'cliente@gmail.com',
            'password' => bcrypt('12345678')
        ])->assignRole('Cliente');

        User::create([
            'name' => 'Prueba Sin Rol',
            'email' => 'sinrol@gmail.com',
            'password' => bcrypt('12345678')
        ]);

        /* User::factory(99)->create(); */
    }
}
